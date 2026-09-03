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
        // 1. TARIK DATA PO
        $po = \App\Models\PurchaseOrder::with([
            'vendor',
            'items.item.itemUoms',
            'items.item.uom'
        ])->where('po_number', $slug)->firstOrFail();

        $pendingItems = $po->items->filter(function ($item) use ($po) {
            $poConvFactor = 1;
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
            // 🔥 OTAK CERDAS: PENCARIAN ALOKASI (CEK SEMUA KEMUNGKINAN KOLOM) 🔥
            // ====================================================================
            $prItemDesc = null;
            $prItemId = $item->purchase_request_item_id;

            // Jika kosong, cari berdasarkan relasi Header PO -> PR
            if (empty($prItemId) && !empty($po->purchase_request_id)) {
                $lacakPrItem = \Illuminate\Support\Facades\DB::table('purchase_request_items')
                                ->where('purchase_request_id', $po->purchase_request_id)
                                ->where('item_id', $item->item_id)
                                ->first();
                if ($lacakPrItem) {
                    $prItemId = $lacakPrItem->id;
                }
            }

            if (!empty($prItemId)) {
                $prItemRow = \Illuminate\Support\Facades\DB::table('purchase_request_items')
                                ->where('id', $prItemId)
                                ->first();
                if ($prItemRow) {
                    // 🔥 SAKTI: Baca dari allocation_notes, JIKA KOSONG baca dari notes, JIKA KOSONG baca description 🔥
                    $prItemDesc = $prItemRow->allocation_notes ?? $prItemRow->notes ?? $prItemRow->description ?? null;
                }
            }

            $isFromSmartRestock = false;
            $poNotes = strtolower($po->notes ?? '');
            $poDesc = strtolower($po->description ?? '');

            // Cek apakah ini berasal dari Smart Restock
            if (str_contains($poNotes, 'auto-restock') || str_contains($poDesc, 'auto-restock') || str_contains($poNotes, 'smart restock') || str_contains($poNotes, 'rombongan')) {
                $isFromSmartRestock = true;
            }
            if (!empty($prItemDesc) && str_contains(strtolower($prItemDesc), 'alokasi')) {
                $isFromSmartRestock = true;
            }

            if ($isFromSmartRestock) {
                $item->is_smart_restock = true;

                if (!empty($prItemDesc) && str_contains(strtolower($prItemDesc), 'alokasi')) {
                    $item->final_description = $prItemDesc;
                } else {
                    // Fallback aman agar proses operasional Gudang tidak terblokir error peringatan
                    $item->final_description = "Rincian Alokasi:\n- Dialokasikan untuk Gudang Utama (Sistem merecovery teks otomatis)";
                }
            } else {
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
    // STORE GR (DENGAN LOGIKA UOM ANTI-TABRAKAN & PARTIAL)
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
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.uom'          => 'nullable|string', // Kunci pembacaan teks dari Frontend
        ]);

        $warehouse = \App\Models\Warehouse::find($request->warehouse_id);
        if ($warehouse && $warehouse->is_frozen) {
            return back()->withInput()->with('error', "GAGAL: Gudang {$warehouse->name} sedang dalam status DIBEKUKAN (Stock Opname). Anda tidak dapat mengeluarkan barang dari gudang ini sampai proses audit selesai!");
        }

        try {
            $newGrNumber = DB::transaction(function () use ($request, $slug, $settingService) {
                $po = \App\Models\PurchaseOrder::with('items')->where('po_number', $slug)->firstOrFail();
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

                // 2. Simpan Dokumen Lampiran
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

                // 3. Looping Rincian Barang
                foreach ($request->items as $itemId => $data) {
                    $inputQty = (float) $data['qty_received'];
                    $targetWarehouseId = !empty($data['warehouse_id']) ? $data['warehouse_id'] : 1;

                    if ($inputQty > 0) {
                        $poItem = \App\Models\PurchaseOrderItem::findOrFail($itemId);
                        $masterItem = \App\Models\Item::with('uom', 'itemUoms')->findOrFail($data['item_id']);
                        $baseUomName = strtoupper(optional($masterItem->uom)->name ?? 'PCS');

                        // ========================================================
                        // 🔥 A. DETEKSI KONVERSI PO ASLI (SUMBER KEBENARAN) 🔥
                        // ========================================================
                        $poConvFactor = 1;
                        $rawPoUom = $poItem->uom ?? $baseUomName;
                        if (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                            $poConvFactor = (float) $matches[1];
                        } elseif (!empty($poItem->uom_id) && $poItem->uom_id != $masterItem->uom_id) {
                            $uomDb = \App\Models\ItemUom::where('id', $poItem->uom_id)->where('item_id', $masterItem->id)->first();
                            if ($uomDb) $poConvFactor = (float) $uomDb->conversion_qty;
                        }
                        $poConvFactorSafe = $poConvFactor > 0 ? $poConvFactor : 1;

                        // ========================================================
                        // 🔥 B. DETEKSI KONVERSI INPUT GR DARI USER (ANTI-TABRAKAN) 🔥
                        // ========================================================
                        $inputConvFactor = 1;
                        $inputUomStr = $data['uom'] ?? $baseUomName; // Membaca teks uom yang disuplai JS Frontend

                        if (preg_match('/\(Isi:\s*([0-9.]+)/i', $inputUomStr, $matches)) {
                            $inputConvFactor = (float) $matches[1];
                        } elseif (!empty($data['uom_id']) && $data['uom_id'] != $masterItem->uom_id) {
                            $uomDb = \App\Models\ItemUom::where('id', $data['uom_id'])->where('item_id', $masterItem->id)->first();
                            if ($uomDb) $inputConvFactor = (float) $uomDb->conversion_qty;
                        }

                        // Bersihkan embel-embel [PO] agar rapi di Database
                        $finalUomString = trim(preg_replace('/ \[PO\]/i', '', $inputUomStr));

                        // ========================================================
                        // 🔥 C. MATEMATIKA KONVERSI MUTLAK (PERBAIKAN UTAMA) 🔥
                        // ========================================================
                        // 1. Konversi angka yang diketik user menjadi Eceran Mutlak
                        $baseQtyReceived = $inputQty * $inputConvFactor;

                        // 2. Berapa potongannya jika dikonversi kembali ke dalam satuan PO?
                        $qtyYangMemotongPO = $baseQtyReceived / $poConvFactorSafe;

                        // 3. Simpan ke database PO
                        $poItem->qty_received = (float)($poItem->qty_received ?? 0) + $qtyYangMemotongPO;
                        $poItem->save();


                        // --- HITUNGAN HARGA & STOK ---
                        $hargaDariPO = (float) ($poItem->unit_price ?? 0);
                        $hargaDasarPerPiece = $hargaDariPO / $poConvFactorSafe;

                        $qtyInt = (int) $baseQtyReceived;
                        $rawSnList = $data['sn'] ?? [];

                        $autoCount = 0;
                        foreach ($rawSnList as $sn) {
                            if (strtoupper(trim($sn)) === '[AUTO]') $autoCount++;
                        }

                        $generatedSns = [];
                        if ($autoCount > 0) $generatedSns = $this->generateSnBatch($masterItem->code, $autoCount);

                        $finalSnList = [];
                        $autoIdx = 0;
                        foreach ($rawSnList as $sn) {
                            if (strtoupper(trim($sn)) === '[AUTO]') {
                                $finalSnList[] = $generatedSns[$autoIdx] ?? ($masterItem->code . '-ERR-' . uniqid());
                                $autoIdx++;
                            } else {
                                $finalSnList[] = trim($sn);
                            }
                        }
                        $finalSnList = array_filter($finalSnList);

                        if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                            foreach ($finalSnList as $sn) {
                                \DB::table('item_serials')->insert([
                                    'item_id'          => $masterItem->id,
                                    'goods_receipt_id' => $gr->id,
                                    'warehouse_id'     => $targetWarehouseId,
                                    'serial_number'    => $sn,
                                    'status'           => 'AVAILABLE',
                                    'created_at'       => now(),
                                    'updated_at'       => now(),
                                ]);
                            }
                        }

                        $catatanAsli = $data['notes'] ?? null;

                        // 4. SIMPAN DETAIL GR
                        \App\Models\GoodsReceiptItem::create([
                            'goods_receipt_id'       => $gr->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id'                => $data['item_id'],
                            'qty_received'           => $inputQty,
                            'uom_id'                 => $data['uom_id'] ?? null,
                            'uom'                    => $finalUomString,
                            'condition_id'           => $data['condition_id'],
                            'notes'                  => $catatanAsli,
                        ]);

                        // 5. UPDATE STOK & MUTASI
                        if ($masterItem->is_stockable ?? true) {
                            $globalBalanceBefore = (float) $masterItem->current_stock;
                            $namaSpesifik = strip_tags($poItem->description ?? $masterItem->name);
                            $noteMutasi = "Masuk: {$namaSpesifik} (Ref PO: {$po->po_number})";

                            if ($catatanAsli) {
                                $noteMutasi .= " - " . \Illuminate\Support\Str::limit(strip_tags($catatanAsli), 100);
                            }

                            $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                                                    ->where('warehouse_id', $targetWarehouseId)
                                                                    ->first();

                            if (!$invStock) {
                                $invStock = \App\Models\InventoryStock::create([
                                    'company_id'   => $po->bill_to_company_id,
                                    'warehouse_id' => $targetWarehouseId,
                                    'item_id'      => $masterItem->id,
                                    'stock_qty'    => 0,
                                    'unit_price'   => $hargaDasarPerPiece,
                                ]);
                            }

                            $balanceBefore = (float) $invStock->stock_qty;
                            $balanceAfter  = $balanceBefore + $baseQtyReceived;

                            try {
                                \App\Models\InventoryMovement::create([
                                    'inventory_stock_id' => $invStock->id,
                                    'type'               => 'IN',
                                    'qty'                => $baseQtyReceived,
                                    'balance_before'     => $balanceBefore,
                                    'balance_after'      => $balanceAfter,
                                    'reference_number'   => $grNumber,
                                    'notes'              => $noteMutasi,
                                    'created_by'         => auth()->id(),
                                ]);
                            } catch (\Exception $e) {}

                            // CATAT KE STOCK MUTATIONS JUGA SEBAGAI TRACKER
                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $targetWarehouseId,
                                'type'             => 'IN',
                                'qty'              => $baseQtyReceived,
                                'balance_before'   => $globalBalanceBefore,
                                'balance_after'    => $globalBalanceBefore + $baseQtyReceived,
                                'reference_number' => $grNumber,
                                'notes'            => $noteMutasi,
                                'created_by'       => auth()->id(),
                            ]);

                            $invStock->update([
                                'stock_qty'  => $balanceAfter,
                                'unit_price' => $hargaDasarPerPiece > 0 ? $hargaDasarPerPiece : $invStock->unit_price
                            ]);

                            $masterItem->update(['current_stock' => $globalBalanceBefore + $baseQtyReceived]);
                        }
                    }
                }

                // Update Status PO
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

                return $grNumber;
            });

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
    // 🔥 TAMPILKAN HALAMAN DETAIL GR (DETEKTIF GUDANG MUTASI STOK)
    // =========================================================================
    public function show($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'purchaseOrder.vendor', 'purchaseOrder.company', 'items.item.uom',
            'items.item.itemUoms', 'items.purchaseOrderItem', 'items.condition', 'attachments'
        ])->where('gr_number', $slug)->firstOrFail();

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

            $whName = 'Gudang Utama / Default';

            // 🔥 DETEKTIF GUDANG: Baca Tabel Stock Mutations 🔥
            try {
                $mutation = \Illuminate\Support\Facades\DB::table('stock_mutations')
                    ->where('reference_number', $gr->gr_number)
                    ->where('item_id', $grItem->item_id)
                    ->where('type', 'IN')
                    ->first();

                if ($mutation && $mutation->warehouse_id) {
                    $wh = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $mutation->warehouse_id)->first();
                    if ($wh) {
                        $whName = $wh->name;
                    }
                } else {
                    // Fallback dari Catatan Teks PR Lama
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
    // 🔥 CETAK GABUNG (UNTUK VENDOR / KURIR)
    // =========================================================================
    public function printVendor($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $whName = 'Gudang Utama / Default';
            try {
                $mutation = \Illuminate\Support\Facades\DB::table('stock_mutations')
                    ->where('reference_number', $gr->gr_number)
                    ->where('item_id', $grItem->item_id)
                    ->where('type', 'IN')
                    ->first();

                if ($mutation && $mutation->warehouse_id) {
                    $wh = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $mutation->warehouse_id)->first();
                    if ($wh) $whName = $wh->name;
                }
            } catch (\Exception $e) {}
            $grItem->warehouse_name_display = $whName;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gr.print_vendor', compact('gr'))->setPaper('A4', 'portrait');
        return $pdf->stream('GR_Vendor_' . str_replace('/', '_', $gr->gr_number) . '.pdf');
    }

    // =========================================================================
    // 🔥 CETAK DISTRIBUSI PECAH (UNTUK INTERNAL GUDANG)
    // =========================================================================
    public function printInternal($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $whName = 'Gudang Utama / Default';
            try {
                $mutation = \Illuminate\Support\Facades\DB::table('stock_mutations')
                    ->where('reference_number', $gr->gr_number)
                    ->where('item_id', $grItem->item_id)
                    ->where('type', 'IN')
                    ->first();

                if ($mutation && $mutation->warehouse_id) {
                    $wh = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $mutation->warehouse_id)->first();
                    if ($wh) $whName = $wh->name;
                }
            } catch (\Exception $e) {}
            $grItem->warehouse_name_display = $whName;
        }

        $groupedItems = $gr->items->groupBy('warehouse_name_display');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gr.print_internal', compact('gr', 'groupedItems'))->setPaper('A4', 'portrait');
        return $pdf->stream('GR_Internal_' . str_replace('/', '_', $gr->gr_number) . '.pdf');
    }

    // =========================================================================
    // 🔥 CETAK STANDAR LAINNYA
    // =========================================================================
    public function print($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'creator', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $whName = 'Gudang Utama / Default';
            try {
                $mutation = \Illuminate\Support\Facades\DB::table('stock_mutations')
                    ->where('reference_number', $gr->gr_number)
                    ->where('item_id', $grItem->item_id)
                    ->where('type', 'IN')
                    ->first();

                if ($mutation && $mutation->warehouse_id) {
                    $wh = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $mutation->warehouse_id)->first();
                    if ($wh) $whName = $wh->name;
                }
            } catch (\Exception $e) {}
            $grItem->warehouse_name_display = $whName;
        }

        $groupedItems = $gr->items->groupBy('warehouse_name_display');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gr.print', compact('gr', 'groupedItems'))->setPaper('A4', 'portrait');
        return $pdf->stream('Goods_Receipt_' . str_replace('/', '_', $gr->gr_number) . '.pdf');
    }

    // public function print($slug)
    // {
    //     $gr = \App\Models\GoodsReceipt::with([
    //         'items.item.itemUoms',
    //         'items.purchaseOrderItem',
    //         'purchaseOrder.vendor',
    //         'purchaseOrder.company',
    //         'creator',
    //         'items.warehouse',
    //         'items.condition'
    //     ])->where('gr_number', $slug)->firstOrFail();

    //     // 🔥 LOGIKA CERDAS: KELOMPOKKAN ITEM BERDASARKAN NAMA GUDANG 🔥
    //     $groupedItems = $gr->items->groupBy(function ($item) {
    //         return $item->warehouse ? $item->warehouse->name : 'Gudang Utama / Default';
    //     });

    //     // Render PDF menggunakan data yang sudah dikelompokkan
    //     $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gr.print', compact('gr', 'groupedItems'))
    //               ->setPaper('A4', 'portrait');

    //     return $pdf->stream('Goods_Receipt_' . str_replace('/', '_', $gr->gr_number) . '.pdf');
    // }

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
