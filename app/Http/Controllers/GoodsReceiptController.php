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
                      });
            })
            ->latest()
            ->paginate(10);

        $statusIds = \App\Models\Status::where('type', 'PO')
                        ->whereIn('slug', ['issued', 'partial_receipt'])
                        ->pluck('id');

        $readyPOs = \App\Models\PurchaseOrder::with(['vendor', 'company'])
                        ->whereIn('status_id', $statusIds)
                        ->orderBy('updated_at', 'desc')
                        ->get();

        return view('gr.index', compact('grs', 'readyPOs'));
    }

    // ========================================================
    // CREATE GR (MENGGUNAKAN LOGIKA UOM_ID YANG PRESISI)
    // ========================================================
    public function create($slug)
    {
        $po = \App\Models\PurchaseOrder::with([
            'vendor',
            'items.item.itemUoms',
            'items.item.uom'
        ])->where('po_number', $slug)->firstOrFail();

        $pendingItems = $po->items->filter(function ($item) {
            // 🔥 TARIK SATUAN UOM PO SECARA ABSOLUT MENGGUNAKAN ID 🔥
            $poConvFactor = 1;

            if (!empty($item->uom_id)) {
                $uomDb = collect(optional(optional($item->item)->itemUoms))->where('id', $item->uom_id)->first();
                if ($uomDb) {
                    $poConvFactor = (float) $uomDb->conversion_qty;
                }
            } else {
                // Fallback jika uom_id kosong (PO lama)
                $rawPoUom = is_string($item->uom) ? $item->uom : (optional($item->uom)->name ?? $item->getRawOriginal('uom') ?? 'PCS');
                if (preg_match('/Isi\s*[:=]\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                    $poConvFactor = (float) $matches[1];
                }
            }

            // Karena qty_received dan qty_ordered di dalam database tabel PO itu "Apple to Apple" (satuannya sama-sama PO)
            // Jadi kita tinggal kurangkan saja langsung!
            $sisaPoUom = (float)$item->qty_ordered - (float)($item->qty_received ?? 0);

            // Simpan variabel sisa ke dalam objek agar bisa dibaca di Blade
            $item->sisa_po_uom = $sisaPoUom;
            $item->po_conv_factor = $poConvFactor;
            $item->raw_po_uom = $item->uom; // Simpan teks untuk tampilan layar

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
    // STORE GR (DENGAN LOGIKA UOM_ID YANG SAMA KOKOHNYA)
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
        ]);

        try {
            $newGrNumber = DB::transaction(function () use ($request, $slug, $settingService) {
                $po = \App\Models\PurchaseOrder::with('items')->where('po_number', $slug)->firstOrFail();
                $grNumber = $this->generateGrNumber($po->bill_to_company_id);

                $gr = \App\Models\GoodsReceipt::create([
                    'purchase_order_id'    => $po->id,
                    'gr_number'            => $grNumber,
                    'delivery_note_number' => $request->delivery_note_number,
                    'received_date'        => $request->receipt_date,
                    'received_by'          => auth()->id(),
                    'notes'                => $request->notes,
                ]);

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

                foreach ($request->items as $itemId => $data) {
                    $inputQty = (float) $data['qty_received'];

                    if ($inputQty > 0) {
                        $poItem = \App\Models\PurchaseOrderItem::findOrFail($itemId);
                        $masterItem = \App\Models\Item::with('uom', 'itemUoms')->findOrFail($data['item_id']);
                        $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                        // 🔥 LOGIKA UOM DARI PO ITEM 🔥
                        $poConvFactor = 1;
                        if (!empty($poItem->uom_id)) {
                            $uomDb = collect($masterItem->itemUoms)->where('id', $poItem->uom_id)->first();
                            if ($uomDb) $poConvFactor = (float) $uomDb->conversion_qty;
                        } else {
                            $rawPoUom = $poItem->uom ?? 'PCS';
                            if (preg_match('/Isi\s*[:=]\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                                $poConvFactor = (float) $matches[1];
                            }
                        }

                        // 🔥 LOGIKA UOM DARI FORM GR (Yang dipilih user saat terima barang) 🔥
                        $inputConvFactor = 1;
                        $cleanInputUom = 'PCS';
                        $selectedUomId = null; // <-- Siapkan variabel penampung ID

                        if (!empty($data['uom_id'])) {
                            $uomDb = collect($masterItem->itemUoms)->where('id', $data['uom_id'])->first();
                            if ($uomDb) {
                                $selectedUomId = $uomDb->id; // <-- Tangkap ID-nya
                                $inputConvFactor = (float) $uomDb->conversion_qty;
                                $cleanInputUom = $uomDb->uom_name;
                            }
                        } else {
                            // Coba fallback dengan teks jika tidak ada ID
                            $inputUomText = $data['uom'] ?? $poItem->uom;
                            $cleanInputUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $inputUomText));
                            if (preg_match('/Isi\s*[:=]\s*([0-9.]+)/i', $inputUomText, $matches)) {
                                $inputConvFactor = (float) $matches[1];
                            }
                        }

                        // 🔥 RANGKAI TEKS HISTORI UOM 🔥
                        $finalUomString = $cleanInputUom;
                        if ($inputConvFactor > 1) {
                            $finalUomString .= ' (Isi ' . (float)$inputConvFactor . ' ' . $baseUomName . ')';
                        }
                        // Proteksi jika kosong
                        if (empty(trim($finalUomString))) {
                            $finalUomString = $baseUomName ?? 'PCS';
                        }

                        // Pengecekan Batas Maksimal berdasarkan Satuan Dasar (Eceran)
                        $baseQtyReceived = $inputQty * $inputConvFactor;
                        $baseQtyOrdered = $poItem->qty_ordered * $poConvFactor;
                        $baseQtyReceivedSoFar = ($poItem->qty_received ?? 0) * $poConvFactor;
                        $sisaBaseYangBolehDiterima = round($baseQtyOrdered - $baseQtyReceivedSoFar, 4);

                        if (round($baseQtyReceived, 4) > $sisaBaseYangBolehDiterima) {
                            throw new \Exception("Kuantitas terima ({$baseQtyReceived} {$baseUomName}) melebihi sisa pesanan PO!");
                        }

                        // ... (BAGIAN LOGIKA SERIAL NUMBER TETAP SAMA, BIARKAN SAJA) ...
                        // (Saya skip penulisan logika SN di sini agar fokus, kode SN Anda sudah benar)

                        $snString = ''; // <--- TAMBAHKAN BARIS INI SEBAGAI PENYELAMAT

                        $catatanAsli = $data['notes'] ?? null;

                        // 🔥 SIMPAN KEDUANYA KE DATABASE 🔥
                        \App\Models\GoodsReceiptItem::create([
                            'goods_receipt_id'       => $gr->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id'                => $data['item_id'],
                            'qty_received'           => $inputQty,
                            'uom_id'                 => $selectedUomId,  // Simpan ID untuk relasi & query laporan
                            'uom'                    => $finalUomString, // Simpan Teks "Pack (Isi 10)" untuk histori dokumen
                            'condition_id'           => $data['condition_id'],
                            'notes'                  => $catatanAsli,
                        ]);

                        // UPDATE QTY_RECEIVED DI TABEL PO (Harus "Apple to Apple" dengan PO UOM)
                        $poItem->qty_received = ($poItem->qty_received ?? 0) + ($baseQtyReceived / $poConvFactor);
                        $poItem->save();

                        if ($masterItem->is_stockable) {
                            $balanceBefore = (float) $masterItem->current_stock;
                            $balanceAfter  = $balanceBefore + $baseQtyReceived;
                            $namaSpesifik = $poItem->description ?? $masterItem->name;

                            $noteMutasi = $catatanAsli ? "Masuk: {$namaSpesifik} (Ref PO: {$po->po_number}) - {$catatanAsli}" : "Masuk: {$namaSpesifik} (Ref PO: {$po->po_number})";
                            if ($snString) $noteMutasi .= " [SN: {$snString}]";

                            \App\Models\InventoryStock::create([
                                'company_id'       => $po->bill_to_company_id,
                                'warehouse_id'     => $request->warehouse_id ?? 1,
                                'item_id'          => $masterItem->id,
                                'stock_qty'        => $baseQtyReceived,
                                'reference_number' => $grNumber,
                                'notes'            => $noteMutasi,
                            ]);

                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $request->warehouse_id ?? 1,
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

                        if ($masterItem->is_asset) {
                            $yearMonth = date('Y/m');
                            $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
                            $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;

                            for ($i = 0; $i < $qtyInt; $i++) {
                                $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
                                $currentSn = $finalSnList[$i] ?? null;

                                \App\Models\FixedAsset::create([
                                    'asset_number'     => $assetNumber,
                                    'serial_number'    => $currentSn,
                                    'item_id'          => $masterItem->id,
                                    'warehouse_id'     => $request->warehouse_id ?? 1,
                                    'company_id'       => $po->bill_to_company_id,
                                    'name'             => $poItem->description ?? $masterItem->name,
                                    'goods_receipt_id' => $gr->id,
                                    'acquisition_date' => $request->receipt_date,
                                    'purchase_price'   => ($poItem->unit_price / $poConvFactor),
                                    'status_id'        => \App\Models\Status::where('type', 'AST')->where('slug', 'available')->first()->id ?? 1,
                                    'notes'            => $catatanAsli ?? 'Aset masuk dari GR: ' . $grNumber
                                ]);
                                $nextSeq++;
                            }
                        }
                    }
                }

                $po->refresh();
                $allFullyReceived = true;
                foreach ($po->items as $item) {
                    // Cek Sisa Penerimaan (Bandingkan QTY Ordered & Received yang satuannya sudah Apple to Apple)
                    if (round($item->qty_received ?? 0, 4) < round($item->qty_ordered, 4)) {
                        $allFullyReceived = false; break;
                    }
                }

                $newStatusSlug = $allFullyReceived ? 'fully_received' : 'partial_receipt';
                $statusTarget = \App\Models\Status::where('type', 'PO')->where('slug', $newStatusSlug)->first();
                if ($statusTarget) $po->update(['status_id' => $statusTarget->id]);

                $statusText = $allFullyReceived ? 'Penerimaan Penuh (Fully Received)' : 'Penerimaan Parsial (Partial Receipt)';
                \App\Models\PurchaseOrderHistory::create(['purchase_order_id' => $po->id, 'user_id' => auth()->id(), 'action' => 'GOODS RECEIPT', 'note' => "Barang telah tiba. Status: {$statusText}.\nNo Surat Jalan: {$request->delivery_note_number}\nNo GR: {$grNumber}"]);

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

            return redirect()->route('gr.index')->with(['success' => 'Penerimaan Barang & Serial Number berhasil disimpan!', 'print_url' => route('gr.print', $newGrNumber), 'new_gr' => $newGrNumber]);
        } catch (\Exception $e) {
            \Log::error('Error Simpan GR: ' . $e->getMessage() . " di baris " . $e->getLine());
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'purchaseOrder.vendor',
            'purchaseOrder.company',
            'items.item.uom',
            'items.item.serials' => function($query) use ($slug) {
                $query->where('serial_number', 'like', "%")
                    ->orWhere('notes', 'like', "%")
                    ->orderBy('id', 'asc');
            },
            'items.purchaseOrderItem',
            'items.condition',
            'attachments',
            'receiver'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $grItem->registered_sns = \DB::table('item_serials')
                ->where('item_id', $grItem->item_id)
                ->where('goods_receipt_id', $gr->id)
                ->pluck('serial_number')
                ->toArray();
        }

        return view('gr.show', compact('gr'));
    }

    public function print($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'po.vendor',
            'po.company',
            'items.item',
            'items.condition',
            'items.purchaseOrderItem',
            'receiver'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $grItem->registered_sns = \DB::table('item_serials')
                ->where('item_id', $grItem->item_id)
                ->where('goods_receipt_id', $gr->id)
                ->pluck('serial_number')
                ->toArray();
        }

        return view('gr.print', compact('gr'));
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
            return $masterItem && ($masterItem->is_asset || $masterItem->is_trackable);
        });

        if ($labelItems->isEmpty()) {
            return back()->with('error', 'Tidak ada Aset Tetap atau Inventaris Minor yang perlu dicetak labelnya pada dokumen GR ini.');
        }

        return view('gr.print_labels', compact('gr', 'labelItems'));
    }
}
