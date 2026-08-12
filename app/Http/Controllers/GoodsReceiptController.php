<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\ItemCondition;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoodsReceiptController extends Controller
{
    private function generateGrNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company && $company->code ? strtoupper($company->code) : 'UMUM';

        $year = date('Y');
        $month = date('m');

        $prefix = "GR-{$companyCode}-{$year}-{$month}-";

        $lastGr = \App\Models\GoodsReceipt::where('gr_number', 'LIKE', "{$prefix}%")
                    ->orderBy('id', 'desc')
                    ->first();

        $nextSequence = $lastGr ? ((int) substr($lastGr->gr_number, -4)) + 1 : 1;

        return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    private function generateSnBatch($itemCode, $countNeeded)
    {
        if ($countNeeded <= 0) return [];

        $snPrefix = $itemCode . '-' . date('Ym') . '-';

        $lastRecord = \DB::table('item_serials')
                        ->where('serial_number', 'like', "{$snPrefix}%")
                        ->orderBy('serial_number', 'desc')
                        ->lockForUpdate()
                        ->first();

        $nextSeq = $lastRecord ? ((int) substr($lastRecord->serial_number, -4)) + 1 : 1;

        $generatedSns = [];
        for ($i = 0; $i < $countNeeded; $i++) {
            $generatedSns[] = $snPrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            $nextSeq++;
        }

        return $generatedSns;
    }

    public function index(Request $request)
    {
        $search = $request->input('search');

        $grs = \App\Models\GoodsReceipt::with(['po.vendor', 'receiver', 'items'])
            ->withCount('returnToVendors')
            ->when($search, function ($query) use ($search) {
                $query->where('gr_number', 'like', "%{$search}%")
                      ->orWhere('delivery_note_number', 'like', "%{$search}%")
                      ->orWhereHas('po', function ($q) use ($search) {
                          $q->where('po_number', 'like', "%{$search}%")
                            ->orWhereHas('vendor', function ($q2) use ($search) {
                                $q2->where('name', 'like', "%{$search}%");
                            });
                      })
                      // 🔥 TAMBAHAN: Pencarian berdasarkan Nama Barang di tabel riwayat GR 🔥
                      ->orWhereHas('items.item', function ($q3) use ($search) {
                          $q3->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()
            ->paginate(10);

        $statusIds = \App\Models\Status::where('type', 'PO')
                        ->whereIn('slug', ['issued', 'partial_receipt'])
                        ->pluck('id');

        // 🔥 TAMBAHAN: Panggil relasi 'items.item' agar nama barang bisa dilempar ke Modal HTML 🔥
        $readyPOs = \App\Models\PurchaseOrder::with(['vendor', 'company', 'items.item'])
                        ->whereIn('status_id', $statusIds)
                        ->orderBy('updated_at', 'desc')
                        ->get();

        return view('gr.index', compact('grs', 'readyPOs'));
    }

    public function create($slug)
    {
        // 1. TARIK DATA PO TANPA RELASI PR (Bypass agar tidak ada error RelationNotFound)
        $po = \App\Models\PurchaseOrder::with([
            'vendor',
            'items.item.itemUoms',
            'items.item.uom'
        ])->where('po_number', $slug)->firstOrFail();

        // 🔥 PERHATIAN: Tambahkan "use ($po)" agar closure bisa membaca Header PO
        $pendingItems = $po->items->filter(function ($item) use ($po) {
            $poConvFactor = 1;

            // TANGKAP NAMA SATUAN ASLI DARI MASTER BARANG
            $baseUomName = optional(optional($item->item)->uom)->name ?? 'Unit';

            if (!empty($item->uom_id)) {
                $uomDb = collect(optional(optional($item->item)->itemUoms))->where('id', $item->uom_id)->first();
                if ($uomDb) {
                    $poConvFactor = (float) $uomDb->conversion_qty;
                }
            } else {
                $rawPoUom = is_string($item->uom) ? $item->uom : (optional($item->uom)->name ?? $item->getRawOriginal('uom') ?? $baseUomName);
                if (preg_match('/Isi\s*[:=]\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                    $poConvFactor = (float) $matches[1];
                }
            }

            $sisaPoUom = (float)$item->qty_ordered - (float)($item->qty_received ?? 0);

            $item->sisa_po_uom = $sisaPoUom;
            $item->po_conv_factor = $poConvFactor;
            $item->base_uom_name = $baseUomName;
            $item->raw_po_uom = is_string($item->uom) ? $item->uom : $baseUomName;

            // ====================================================================
            // 🔥 OTAK CERDAS: PENCARIAN ALOKASI SUPER AGRESIF 🔥
            // ====================================================================
            $prItemDesc = null;

            // 1. Coba cari ID PR Item dari baris PO
            $prItemId = $item->purchase_request_item_id;

            // 2. JIKA KOSONG (Link terputus saat Split PO), cari paksa lewat Header PO!
            if (empty($prItemId) && !empty($po->purchase_request_id)) {
                $lacakPrItem = \Illuminate\Support\Facades\DB::table('purchase_request_items')
                                ->where('purchase_request_id', $po->purchase_request_id)
                                ->where('item_id', $item->item_id) // Cocokkan ID Barangnya
                                ->first();
                if ($lacakPrItem) {
                    $prItemId = $lacakPrItem->id;
                }
            }

            // 3. Tarik Teks Catatan dari Database
            if (!empty($prItemId)) {
                $prItemRow = \Illuminate\Support\Facades\DB::table('purchase_request_items')
                                ->where('id', $prItemId)
                                ->first();
                if ($prItemRow) {
                    // 🔥 LANGSUNG TEMBAK KOLOM BARU KITA 🔥
                    $prItemDesc = $prItemRow->allocation_notes ?? null;
                }
            }

            // 4. Detektor Smart Restock
            $isFromSmartRestock = false;
            if (str_contains($po->notes ?? '', 'Auto-Restock') || str_contains($po->description ?? '', 'Auto-Restock')) {
                $isFromSmartRestock = true;
            }
            if (!empty($prItemDesc) && str_contains($prItemDesc, 'Alokasi')) {
                $isFromSmartRestock = true;
            }

            // 5. Eksekusi Tampilan
            if ($isFromSmartRestock) {
                $item->is_smart_restock = true;

                if (!empty($prItemDesc) && str_contains($prItemDesc, 'Alokasi')) {
                    $item->final_description = $prItemDesc;
                } else {
                    $item->final_description = "⚠️ BARANG SMART RESTOCK MULTI-GUDANG.\n(Teks alokasi tidak ditemukan di database. Ini terjadi karena PR dibuat sebelum update kolom. Tolong buat PR Massal yang BARU).";
                }
            } else {
                // PO Biasa / Manual
                $item->is_smart_restock = false;
                $item->final_description = $item->description ?? $item->notes ?? '-';
            }

            return round($sisaPoUom, 4) > 0;
        });

        if ($pendingItems->isEmpty()) {
            return redirect()->route('po.show', $slug)->with('error', 'Semua barang pada PO ini sudah diterima penuh.');
        }

        $conditions = \App\Models\ItemCondition::where('is_active', true)->get();
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        return view('gr.create', compact('po', 'pendingItems', 'conditions', 'warehouses'));
    }

    // ========================================================
    // STORE GR (LENGKAP DENGAN TRANSLATOR [AUTO] & ANTI-PCS)
    // ========================================================
    public function store(Request $request, $slug, \App\Services\SystemSettingService $settingService)
    {
        $request->validate([
            'receipt_date'         => 'required|date|before_or_equal:today',
            'delivery_note_number' => 'required|string|max:255',
            'attachments'          => 'nullable|array',
            'attachments.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'items'                => 'required|array',
            'items.*.qty_received' => 'required|numeric|min:0',
            'items.*.condition_id' => 'required|exists:item_conditions,id',
            'items.*.sn'           => 'nullable|array',
        ], [
            // 🔥 Pesan Kustom Bahasa Indonesia 🔥
            'receipt_date.required'         => 'Tanggal Terima wajib diisi.',
            'receipt_date.before_or_equal'  => 'Tanggal Terima tidak boleh melebihi hari ini.',
            'delivery_note_number.required' => 'No. Surat Jalan (Delivery Note) wajib diisi.',
            'attachments.*.mimes'           => 'Format file lampiran harus berupa PDF, JPG, JPEG, atau PNG.',
            'attachments.*.max'             => 'Ukuran setiap file lampiran maksimal 5MB.',
            'items.required'                => 'Daftar barang tidak boleh kosong.',
            'items.*.qty_received.required' => 'Kuantitas terima harus diisi untuk setiap barang.',
            'items.*.qty_received.numeric'  => 'Kuantitas terima harus berupa angka.',
            'items.*.condition_id.required' => 'Kondisi barang wajib dipilih.',
            'items.*.condition_id.exists'   => 'Kondisi barang yang dipilih tidak valid.',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id', // 👈 TAMBAHKAN INI
        ]);

        try {
            $newGrNumber = DB::transaction(function () use ($request, $slug, $settingService) {
                $po = \App\Models\PurchaseOrder::with('items')->where('po_number', $slug)->firstOrFail();

                // Generate Nomor GR
                $grNumber = $this->generateGrNumber($po->bill_to_company_id);

                // 1. Simpan Header Goods Receipt
                $gr = \App\Models\GoodsReceipt::create([
                    'purchase_order_id'    => $po->id,
                    'gr_number'            => $grNumber,
                    'delivery_note_number' => $request->delivery_note_number,
                    'received_date'        => $request->receipt_date,
                    'received_by'          => auth()->id(),
                    'notes'                => $request->notes,
                ]);

                // 2. Simpan Dokumen Lampiran jika ada
                if ($request->hasFile('attachments')) {
                    $safeGrNumber = str_replace('/', '-', $grNumber);
                    $basePath = $settingService->getAttachmentPath('GR');
                    $targetFolder = $basePath . '/' . $safeGrNumber;
                    $flatFiles = \Illuminate\Support\Arr::flatten([$request->file('attachments')]);

                    foreach ($flatFiles as $file) {
                        if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                            $originalName = $file->getClientOriginalName();
                            $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                            $path = $file->storeAs($targetFolder, $filename, 'public');
                            \App\Models\GoodsReceiptAttachment::create([
                                'goods_receipt_id' => $gr->id,
                                'file_name' => $originalName,
                                'file_path' => str_replace('\\', '/', $path)
                            ]);
                        }
                    }
                }

                // 3. Looping Rincian Barang yang Diterima
                foreach ($request->items as $itemId => $data) {
                    $inputQty = (float) $data['qty_received'];

                    if ($inputQty > 0) {
                        $poItem = \App\Models\PurchaseOrderItem::findOrFail($itemId);
                        $masterItem = \App\Models\Item::with('uom', 'itemUoms')->findOrFail($data['item_id']);

                        // 🔥 Default Master UOM (Pasti bukan PCS jika masternya Galon) 🔥
                        $baseUomName = optional($masterItem->uom)->name ?? 'Unit';

                        // LOGIKA UOM DARI PO ITEM
                        $poConvFactor = 1;
                        if (!empty($poItem->uom_id)) {
                            $uomDb = collect($masterItem->itemUoms)->where('id', $poItem->uom_id)->first();
                            if ($uomDb) $poConvFactor = (float) $uomDb->conversion_qty;
                        } else {
                            $rawPoUom = $poItem->uom ?? $baseUomName;
                            if (preg_match('/Isi\s*[:=]\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                                $poConvFactor = (float) $matches[1];
                            }
                        }

                        $hargaDariPO = (float) ($poItem->unit_price ?? 0);

                        // ====================================================================
                        // 🔥 PERBAIKAN: HITUNG HPP PER SATUAN DASAR (ANTI VALUASI MELEDAK) 🔥
                        // ====================================================================
                        $poConvFactorSafe = $poConvFactor > 0 ? $poConvFactor : 1;
                        $hargaDasarPerPiece = $hargaDariPO / $poConvFactorSafe;

                        // 🔥 LOGIKA UOM PENYIMPANAN YANG SUDAH DIBERSIHKAN 🔥
                        $inputConvFactor = 1;

                        // 🔥 LOGIKA UOM PENYIMPANAN YANG SUDAH DIBERSIHKAN 🔥
                        $inputConvFactor = 1;
                        $selectedUomId = null;
                        $finalUomString = $baseUomName; // Set awal langsung ke Satuan Master

                        if (!empty($data['uom_id'])) {
                            // Jika user memilih dropdown khusus (Misal: Pack, Dus)
                            $uomDb = collect($masterItem->itemUoms)->where('id', $data['uom_id'])->first();
                            if ($uomDb) {
                                $selectedUomId = $uomDb->id;
                                $inputConvFactor = (float) $uomDb->conversion_qty;
                                $finalUomString = $uomDb->uom_name;
                                if ($inputConvFactor > 1) {
                                    $finalUomString .= ' (Isi ' . $inputConvFactor . ' ' . $baseUomName . ')';
                                }
                            }
                        } else {
                            // Jika form tidak mengirimkan UOM, artinya ambil dari PO
                            if (!empty($poItem->uom_id)) {
                                $poUomDb = collect($masterItem->itemUoms)->where('id', $poItem->uom_id)->first();
                                if ($poUomDb) {
                                    $inputConvFactor = (float) $poUomDb->conversion_qty;
                                    $finalUomString = $poUomDb->uom_name;
                                    if ($inputConvFactor > 1) {
                                        $finalUomString .= ' (Isi ' . $inputConvFactor . ' ' . $baseUomName . ')';
                                    }
                                }
                            } elseif (!empty($poItem->uom)) {
                                $inputConvFactor = $poConvFactor;
                                $finalUomString = $poItem->uom;
                            }
                        }

                        // 🔥 FILTER ANTI-PCS TERAKHIR 🔥
                        // Jika hasil akhir string ini adalah 'PCS' atau 'pcs' tapi Master Barang bilang lain,
                        // kita paksa buang 'PCS'-nya dan ubah menjadi satuan Master Barang!
                        $cleanUomCheck = trim(preg_replace('/ \(Isi:?.*\)/i', '', $finalUomString));
                        if (strtoupper($cleanUomCheck) === 'PCS' && strtoupper($baseUomName) !== 'PCS') {
                            $finalUomString = $baseUomName;
                        }

                        // Hitung batas maksimum dalam pecahan terkecil (Base Qty)
                        $baseQtyReceived = $inputQty * $inputConvFactor;
                        $baseQtyOrdered = $poItem->qty_ordered * $poConvFactor;
                        $baseQtyReceivedSoFar = ($poItem->qty_received ?? 0) * $poConvFactor;
                        $sisaBaseYangBolehDiterima = round($baseQtyOrdered - $baseQtyReceivedSoFar, 4);

                        if (round($baseQtyReceived, 4) > $sisaBaseYangBolehDiterima) {
                            throw new \Exception("Kuantitas terima untuk barang '{$masterItem->name}' ({$baseQtyReceived} {$baseUomName}) melebihi sisa pesanan PO!");
                        }

                        // ====================================================================
                        // 🔥 BAGIAN SAKTI: LOGIKA PENERJEMAH & TRANSLATOR TAG [AUTO] SN 🔥
                        // ====================================================================
                        $qtyInt = (int) $baseQtyReceived;
                        $rawSnList = $data['sn'] ?? [];

                        // Count berapa tag '[AUTO]' yang dikirim dari form
                        $autoCount = 0;
                        foreach ($rawSnList as $sn) {
                            if (strtoupper(trim($sn)) === '[AUTO]') {
                                $autoCount++;
                            }
                        }

                        // Generate kumpulan SN asli dari sistem jika ada [AUTO]
                        $generatedSns = [];
                        if ($autoCount > 0) {
                            $generatedSns = $this->generateSnBatch($masterItem->code, $autoCount);
                        }

                        // Satukan SN manual/scan dengan SN hasil buatan sistem
                        $finalSnList = [];
                        $autoIdx = 0;
                        foreach ($rawSnList as $sn) {
                            if (strtoupper(trim($sn)) === '[AUTO]') {
                                $finalSnList[] = $generatedSns[$autoIdx] ?? ($masterItem->code . '-ERR-' . uniqid());
                                $autoIdx++;
                            } else {
                                $finalSnList[] = trim($sn); // SN ketik manual atau tembak barcode scan
                            }
                        }

                        // Bersihkan array dan gabungkan menjadi string koma untuk mutasi teks
                        $finalSnList = array_filter($finalSnList);
                        $snString = implode(', ', $finalSnList);

                        // Pengecekan validasi jika barang merupakan tipe Lacak Fisik (Trackable)
                        if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                            if (count($finalSnList) < $qtyInt) {
                                throw new \Exception("Wajib mengisi Serial Number sebanyak {$qtyInt} unit untuk barang {$masterItem->name}!");
                            }

                            // Simpan detail per unit SN ke database tabel item_serials
                            foreach ($finalSnList as $sn) {
                                \DB::table('item_serials')->insert([
                                    'item_id'          => $masterItem->id,
                                    'goods_receipt_id' => $gr->id,
                                    'serial_number'    => $sn,
                                    'status'           => 'AVAILABLE',
                                    'created_at'       => now(),
                                    'updated_at'       => now(),
                                ]);
                            }
                        }
                        // ====================================================================

                        $catatanAsli = $data['notes'] ?? null;

                        // 4. 🔥 SIMPAN KE DATABASE DETAIL ITEM GR (UOM SUDAH BENAR) 🔥
                        \App\Models\GoodsReceiptItem::create([
                            'goods_receipt_id'       => $gr->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id'                => $data['item_id'],
                            'qty_received'           => $inputQty,
                            'uom_id'                 => $selectedUomId,
                            'uom'                    => $finalUomString, // <-- Galon akan masuk ke sini
                            'condition_id'           => $data['condition_id'],
                            'notes'                  => $catatanAsli,
                        ]);

                        // 5. Update Progress Kuantitas Diterima di Dokumen PO Induk
                        $poItem->qty_received = ($poItem->qty_received ?? 0) + ($baseQtyReceived / $poConvFactor);
                        $poItem->save();

                        // 6. Update Stok dan Cetak Kartu Mutasi Logistik
                        if ($masterItem->is_stockable ?? true) {
                            $balanceBefore = (float) $masterItem->current_stock;
                            $balanceAfter  = $balanceBefore + $baseQtyReceived;

                            // 🔥 AMBIL ID GUDANG DARI BARIS ITEM (BUKAN DARI HEADER LAGI) 🔥
                            $targetWarehouseId = $data['warehouse_id'] ?? 1;

                            $namaSpesifik = strip_tags($poItem->description ?? $masterItem->name);
                            $noteMutasi = "Masuk: {$namaSpesifik} (Ref PO: {$po->po_number})";

                            if ($catatanAsli) {
                                $noteMutasi .= " - " . \Illuminate\Support\Str::limit(strip_tags($catatanAsli), 100);
                            }

                           \App\Models\InventoryStock::create([
                                'company_id'       => $po->bill_to_company_id,
                                'warehouse_id'     => $targetWarehouseId, // 👈 SUDAH DIUBAH
                                'item_id'          => $masterItem->id,
                                'stock_qty'        => $baseQtyReceived,
                                'unit_price'       => $hargaDasarPerPiece,
                                'reference_number' => $grNumber,
                                'notes'            => $noteMutasi,
                            ]);

                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $targetWarehouseId, // 👈 SUDAH DIUBAH
                                'type'             => 'IN',
                                'qty'              => $baseQtyReceived,
                                'balance_before'   => $balanceBefore,
                                'balance_after'    => $balanceAfter,
                                'reference_number' => $grNumber,
                                'notes'            => $noteMutasi,
                                'created_by'       => auth()->id(),
                            ]);

                            $masterItem->update(['current_stock' => $balanceAfter]);
                        }
                    }
                }

                // 7. Otomatis Update Status PO Berdasarkan Progress Penerimaan Fisik
                $po->refresh();
                $allFullyReceived = true;
                foreach ($po->items as $item) {
                    if (round($item->qty_received ?? 0, 4) < round($item->qty_ordered, 4)) {
                        $allFullyReceived = false; break;
                    }
                }

                $newStatusSlug = $allFullyReceived ? 'fully_received' : 'partial_receipt';
                $statusTarget = \App\Models\Status::where('type', 'PO')->where('slug', $newStatusSlug)->first();
                if ($statusTarget) $po->update(['status_id' => $statusTarget->id]);

                $statusText = $allFullyReceived ? 'Penerimaan Penuh (Fully Received)' : 'Penerimaan Parsial (Partial Receipt)';
                \App\Models\PurchaseOrderHistory::create(['purchase_order_id' => $po->id, 'user_id' => auth()->id(), 'action' => 'GOODS RECEIPT', 'note' => "Barang telah tiba. Status: {$statusText}.\nNo Surat Jalan: {$request->delivery_note_number}\nNo GR: {$grNumber}"]);

                // 8. Logika Sinkronisasi dengan Purchase Request (PR) Induk
                if ($po->purchase_request_id) {
                    $pr = \App\Models\PurchaseRequest::with('items')->find($po->purchase_request_id);
                    if ($pr) {
                        $semuaDipesan = true;
                        foreach($pr->items as $prItem) {
                            if ($prItem->status === 'APPROVED' && round($prItem->ordered_qty ?? 0, 4) < round($prItem->qty, 4)) { $semuaDipesan = false; break; }
                        }
                        $semuaPoSelesai = true;
                        $relatedPos = \App\Models\PurchaseOrder::with('status')->where('purchase_request_id', $pr->id)->get();
                        foreach($relatedPos as $relatedPo) {
                            if (!in_array(optional($relatedPo->status)->slug, ['fully_received', 'canceled'])) { $semuaPoSelesai = false; break; }
                        }

                        if ($semuaDipesan && $semuaPoSelesai) {
                            $statusSelesaiPr = \App\Models\Status::where('type', 'PR')->where('slug', 'completed')->first();
                            if ($statusSelesaiPr && optional($pr->status)->slug !== 'completed') {
                                $pr->update(['status_id' => $statusSelesaiPr->id]);
                                \App\Models\PurchaseRequestHistory::create(['purchase_request_id' => $pr->id, 'user_id' => auth()->id(), 'action' => 'COMPLETED', 'note' => "Siklus selesai. Gudang penuh. (Ref PO: {$po->po_number})."]);
                            }
                        }
                    }
                }
                return $grNumber;
            });

            // return redirect()->route('gr.index')->with(['success' => 'Penerimaan Barang & Serial Number berhasil disimpan!', 'print_url' => route('gr.print', $newGrNumber), 'new_gr' => $newGrNumber]);

            return redirect()->route('gr.index')->with([
                'success'   => 'Penerimaan Barang & Serial Number berhasil disimpan!',
                'print_url' => route('gr.print_vendor', $newGrNumber)
            ]);


        } catch (\Exception $e) {
            \Log::error('Error Simpan GR: ' . $e->getMessage() . " di baris " . $e->getLine());
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 🔥 TAMPILKAN HALAMAN DETAIL GR
    // =========================================================================
    public function show($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'purchaseOrder.vendor', 'purchaseOrder.company', 'items.item.uom',
            'items.item.itemUoms', 'items.purchaseOrderItem', 'items.condition', 'attachments'
        ])->where('gr_number', $slug)->firstOrFail();

        // 1. Amankan Nama Penerima Secara Paksa
        $receiverName = '-';
        if ($gr->received_by) {
            $user = \Illuminate\Support\Facades\DB::table('users')->where('id', $gr->received_by)->first();
            if ($user) $receiverName = $user->name;
        }
        $gr->receiver_name_display = $receiverName;

        $warehouseNames = [];
        foreach ($gr->items as $grItem) {
            $baseUomName = optional(optional($grItem->item)->uom)->name ?? 'Unit';
            $grItem->clean_uom_name = trim(preg_replace('/ \(Isi:?.*\)/i', '', $grItem->uom ?? $baseUomName));

            // 2. Detektif Gudang (Baca Stok atau Teks PR)
            $whName = 'Gudang Utama / Default';
            try {
                $mov = \Illuminate\Support\Facades\DB::table('inventory_movements')
                    ->leftJoin('inventory_stocks', 'inventory_movements.inventory_stock_id', '=', 'inventory_stocks.id')
                    ->where(function($q) use ($gr) {
                        $q->where('inventory_movements.reference_number', $gr->gr_number)
                          ->orWhere('inventory_movements.reference_number', (string) $gr->id);
                    })->where('inventory_stocks.item_id', $grItem->item_id)
                    ->select('inventory_movements.warehouse_id')->first();
                    
                if ($mov && $mov->warehouse_id) {
                    $wh = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $mov->warehouse_id)->first();
                    if ($wh) $whName = $wh->name;
                } else {
                    $poItem = $grItem->purchaseOrderItem;
                    if ($poItem && $poItem->purchase_request_item_id) {
                        $prItem = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('id', $poItem->purchase_request_item_id)->first();
                        if ($prItem && $prItem->allocation_notes) {
                            if (preg_match('/untuk\s+(Gudang.*?)(?:\n|\r|,|$)/i', $prItem->allocation_notes, $matches)) {
                                $whName = trim($matches[1]);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {}
            
            $grItem->warehouse_name_display = $whName;
            $warehouseNames[] = $whName;
        }

        $uniqueWarehouses = collect($warehouseNames)->unique();
        $globalWarehouse = $uniqueWarehouses->count() > 1 ? 'Multi-Gudang (Lihat Tabel)' : ($uniqueWarehouses->first() ?? 'Gudang Utama');

        return view('gr.show', compact('gr', 'globalWarehouse'));
    }

    // =========================================================================
    // 🔥 1. CETAK GABUNG (UNTUK VENDOR / KURIR)
    // =========================================================================
    public function printVendor($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        $receiverName = '-';
        if ($gr->received_by) {
            $user = \Illuminate\Support\Facades\DB::table('users')->where('id', $gr->received_by)->first();
            if ($user) $receiverName = $user->name;
        }
        $gr->receiver_name_display = $receiverName;

        foreach ($gr->items as $grItem) {
            $whName = 'Gudang Utama / Default';
            try {
                $mov = \Illuminate\Support\Facades\DB::table('inventory_movements')
                    ->leftJoin('inventory_stocks', 'inventory_movements.inventory_stock_id', '=', 'inventory_stocks.id')
                    ->where(function($q) use ($gr) {
                        $q->where('inventory_movements.reference_number', $gr->gr_number)->orWhere('inventory_movements.reference_number', (string) $gr->id);
                    })->where('inventory_stocks.item_id', $grItem->item_id)
                    ->select('inventory_movements.warehouse_id')->first();
                if ($mov && $mov->warehouse_id) {
                    $wh = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $mov->warehouse_id)->first();
                    if ($wh) $whName = $wh->name;
                } else {
                    $poItem = $grItem->purchaseOrderItem;
                    if ($poItem && $poItem->purchase_request_item_id) {
                        $prItem = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('id', $poItem->purchase_request_item_id)->first();
                        if ($prItem && $prItem->allocation_notes) {
                            if (preg_match('/untuk\s+(Gudang.*?)(?:\n|\r|,|$)/i', $prItem->allocation_notes, $matches)) $whName = trim($matches[1]);
                        }
                    }
                }
            } catch (\Exception $e) {}
            $grItem->warehouse_name_display = $whName;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gr.print_vendor', compact('gr'))->setPaper('A4', 'portrait');
        return $pdf->stream('GR_Vendor_' . str_replace('/', '_', $gr->gr_number) . '.pdf');
    }

    // =========================================================================
    // 🔥 2. CETAK DISTRIBUSI PECAH (UNTUK INTERNAL GUDANG)
    // =========================================================================
    public function printInternal($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        $receiverName = '-';
        if ($gr->received_by) {
            $user = \Illuminate\Support\Facades\DB::table('users')->where('id', $gr->received_by)->first();
            if ($user) $receiverName = $user->name;
        }
        $gr->receiver_name_display = $receiverName;

        foreach ($gr->items as $grItem) {
            $whName = 'Gudang Utama / Default';
            try {
                $mov = \Illuminate\Support\Facades\DB::table('inventory_movements')
                    ->leftJoin('inventory_stocks', 'inventory_movements.inventory_stock_id', '=', 'inventory_stocks.id')
                    ->where(function($q) use ($gr) {
                        $q->where('inventory_movements.reference_number', $gr->gr_number)->orWhere('inventory_movements.reference_number', (string) $gr->id);
                    })->where('inventory_stocks.item_id', $grItem->item_id)
                    ->select('inventory_movements.warehouse_id')->first();
                if ($mov && $mov->warehouse_id) {
                    $wh = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $mov->warehouse_id)->first();
                    if ($wh) $whName = $wh->name;
                } else {
                    $poItem = $grItem->purchaseOrderItem;
                    if ($poItem && $poItem->purchase_request_item_id) {
                        $prItem = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('id', $poItem->purchase_request_item_id)->first();
                        if ($prItem && $prItem->allocation_notes) {
                            if (preg_match('/untuk\s+(Gudang.*?)(?:\n|\r|,|$)/i', $prItem->allocation_notes, $matches)) $whName = trim($matches[1]);
                        }
                    }
                }
            } catch (\Exception $e) {}
            $grItem->warehouse_name_display = $whName;
        }

        $groupedItems = $gr->items->groupBy('warehouse_name_display');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gr.print_internal', compact('gr', 'groupedItems'))->setPaper('A4', 'portrait');
        return $pdf->stream('GR_Internal_' . str_replace('/', '_', $gr->gr_number) . '.pdf');
    }

    public function print($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms',
            'items.purchaseOrderItem',
            'purchaseOrder.vendor',
            'purchaseOrder.company',
            'creator',
            'items.warehouse',
            'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        // 🔥 LOGIKA CERDAS: KELOMPOKKAN ITEM BERDASARKAN NAMA GUDANG 🔥
        $groupedItems = $gr->items->groupBy(function ($item) {
            return $item->warehouse ? $item->warehouse->name : 'Gudang Utama / Default';
        });

        // Render PDF menggunakan data yang sudah dikelompokkan
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gr.print', compact('gr', 'groupedItems'))
                  ->setPaper('A4', 'portrait');

        return $pdf->stream('Goods_Receipt_' . str_replace('/', '_', $gr->gr_number) . '.pdf');
    }

    public function printLabels($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'po.company',
            'items.item',
            'items.purchaseOrderItem'
        ])->where('gr_number', $slug)->firstOrFail();

        $labelItems = $gr->items->filter(function ($grItem) {
            $masterItem = $grItem->item;
            // 🔥 PERBAIKAN: Hanya cek is_trackable, tidak ada lagi is_asset
            return $masterItem && $masterItem->is_trackable;
        });

        if ($labelItems->isEmpty()) {
            return back()->with('error', 'Tidak ada Inventaris yang perlu dicetak label SN-nya pada dokumen GR ini.');
        }

        return view('gr.print_labels', compact('gr', 'labelItems'));
    }


    


}
