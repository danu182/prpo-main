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
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

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

    private function healDatabaseColumns()
    {
        if (!Schema::hasColumn('purchase_order_items', 'uom')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->string('uom')->nullable()->after('uom_id');
            });
        }
        if (!Schema::hasColumn('goods_receipt_items', 'uom')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                $table->string('uom')->nullable()->after('uom_id');
            });
        }
    }

    // =========================================================================
    // 🔥 MESIN PENYEMBUH MUTLAK: MENGHIDUPKAN KEMBALI PO YANG HILANG 🔥
    // =========================================================================
    private function deepHealPoQtyReceived()
    {
        $poItems = \Illuminate\Support\Facades\DB::table('purchase_order_items')->get();
        foreach ($poItems as $poItem) {
            $grItems = \Illuminate\Support\Facades\DB::table('goods_receipt_items')
                ->where('purchase_order_item_id', $poItem->id)
                ->get();

            $masterItem = \Illuminate\Support\Facades\DB::table('items')->where('id', $poItem->item_id)->first();
            $baseUomId = $masterItem->uom_id ?? null;

            // Cari Faktor Konversi PO
            $poConvFactor = 1;
            if ($poItem->uom_id && $poItem->uom_id != $baseUomId) {
                $alt = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $poItem->uom_id)->first();
                if ($alt) $poConvFactor = (float)$alt->conversion_qty;
            }

            $totalQtyReceivedInPoUnit = 0;
            foreach ($grItems as $grItem) {
                $grConvFactor = 1;
                if ($grItem->uom_id && $grItem->uom_id != $baseUomId) {
                    $altGr = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $grItem->uom_id)->first();
                    if ($altGr) $grConvFactor = (float)$altGr->conversion_qty;
                } elseif (preg_match('/\(Isi:\s*([0-9.]+)/i', $grItem->uom, $m)) {
                    $grConvFactor = (float)$m[1];
                }

                $baseQty = (float)$grItem->qty_received * $grConvFactor;
                $poQty = $baseQty / ($poConvFactor > 0 ? $poConvFactor : 1);
                $totalQtyReceivedInPoUnit += $poQty;
            }

            // Benahi kolom qty_received PO yang rusak
            if (round((float)$poItem->qty_received, 4) !== round($totalQtyReceivedInPoUnit, 4)) {
                \Illuminate\Support\Facades\DB::table('purchase_order_items')
                    ->where('id', $poItem->id)
                    ->update(['qty_received' => round($totalQtyReceivedInPoUnit, 4)]);
            }
        }

        // Buka Kunci PO yang Selesai Prematur
        $pos = \App\Models\PurchaseOrder::with('items')->whereHas('status', function($q){
            $q->whereNotIn('slug', ['draft', 'pending_approval', 'rejected', 'canceled', 'cancelled']);
        })->get();

        $partialStatus = \App\Models\Status::where('type', 'PO')
            ->whereIn('slug', ['partial_receipt', 'partial_received'])
            ->orWhere('name', 'like', '%Parsial%')->first();

        $fullStatus = \App\Models\Status::where('type', 'PO')
            ->whereIn('slug', ['fully_received', 'completed'])->first();

        foreach ($pos as $po) {
            $allFull = true;
            $anyRcv = false;
            foreach ($po->items as $i) {
                if (round((float)$i->qty_received, 4) > 0) $anyRcv = true;
                if (round((float)$i->qty_received, 4) < round((float)$i->qty_ordered, 4)) {
                    $allFull = false;
                }
            }

            if ($allFull && $fullStatus && $po->status_id != $fullStatus->id) {
                $po->update(['status_id' => $fullStatus->id]);
            } elseif (!$allFull && $anyRcv && $partialStatus && $po->status_id != $partialStatus->id) {
                $po->update(['status_id' => $partialStatus->id]);
            }
        }
    }

    public function index(Request $request)
    {
        $this->healDatabaseColumns();
        $this->deepHealPoQtyReceived(); // AUTO-HEAL AKTIF: MENGEMBALIKAN PO YANG HILANG

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
                      ->orWhereHas('items.item', function ($q3) use ($search) {
                          $q3->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()
            ->paginate(10);

        $statusIds = \App\Models\Status::where('type', 'PO')
                        ->whereIn('slug', ['issued', 'partial_receipt', 'partial_received'])
                        ->orWhere('name', 'like', '%Parsial%')
                        ->pluck('id');

        $readyPOs = \App\Models\PurchaseOrder::with(['vendor', 'company', 'items.item'])
                        ->whereIn('status_id', $statusIds)
                        ->orderBy('updated_at', 'desc')
                        ->get();

        return view('gr.index', compact('grs', 'readyPOs'));
    }

    public function create($slug)
    {
        $this->healDatabaseColumns();

        $po = \App\Models\PurchaseOrder::with([
            'vendor',
            'items.item.itemUoms',
            'items.item.uom'
        ])->where('po_number', $slug)->firstOrFail();

        $pendingItems = $po->items->filter(function ($item) use ($po) {
            $masterItem = $item->item;
            $baseUomId = $masterItem->uom_id ?? null;
            $baseUomDb = \Illuminate\Support\Facades\DB::table('uoms')->where('id', $baseUomId)->first();
            $baseUomName = strtoupper($baseUomDb->name ?? 'PCS');

            // ====================================================================
            // 🔥 NATIVE DB ABSOLUT: PRIORITASKAN ID KEMASAN AGAR TIDAK MUNGKIN SALAH
            // ====================================================================
            $nativePoItem = \Illuminate\Support\Facades\DB::table('purchase_order_items')->where('id', $item->id)->first();

            $poUomId = $nativePoItem->uom_id ?? null;
            $rawPoUom = $nativePoItem->uom ?? null;

            if (empty($rawPoUom) && !empty($nativePoItem->purchase_request_item_id)) {
                $prItemRow = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('id', $nativePoItem->purchase_request_item_id)->first();
                if ($prItemRow && Schema::hasColumn('purchase_request_items', 'uom')) {
                    $rawPoUom = $prItemRow->uom;
                }
            }

            if (empty($rawPoUom)) $rawPoUom = $baseUomName;

            if (is_string($rawPoUom) && str_starts_with(trim($rawPoUom), '{')) {
                $uomObj = json_decode($rawPoUom, true);
                if ($uomObj) {
                    $uomObjLower = array_change_key_case($uomObj, CASE_LOWER);
                    $rawPoUom = $uomObjLower['uom_name'] ?? $uomObjLower['name'] ?? $uomObjLower['code'] ?? $baseUomName;
                }
            }

            $poConvFactor = 1;
            if (preg_match('/\(Isi:?\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                $poConvFactor = (float) $matches[1];
            }

            $cleanPoUom = strtoupper(trim(preg_replace('/ \(Isi:.*\)/i', '', $rawPoUom)));
            $cleanPoUom = trim(preg_replace('/ \[PO\]| \[PR\]| \[GR\]/i', '', $cleanPoUom));

            // PRIORITAS 1: NATIVE ID
            if (!empty($poUomId) && $poUomId != $baseUomId) {
                $matchedAlt = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $poUomId)->first();
                if ($matchedAlt) {
                    $poConvFactor = (float) $matchedAlt->conversion_qty;
                    $cleanPoUom = strtoupper($matchedAlt->uom_name);
                }
            } else {
                // PRIORITAS 2: TEXT MATCHING (ANTI-OVERWRITE ISI)
                if ($cleanPoUom !== $baseUomName && $cleanPoUom !== 'PCS' && $cleanPoUom !== 'UNIT') {
                    $query = \Illuminate\Support\Facades\DB::table('item_uoms')
                        ->where('item_id', $masterItem->id)
                        ->whereRaw('UPPER(uom_name) = ?', [$cleanPoUom]);

                    if ($poConvFactor > 1) {
                        $query->where('conversion_qty', $poConvFactor);
                    }

                    $matchedAlt = $query->first();

                    if (!$matchedAlt && $poConvFactor == 1) {
                        $matchedAlt = \Illuminate\Support\Facades\DB::table('item_uoms')
                            ->where('item_id', $masterItem->id)
                            ->whereRaw('UPPER(uom_name) = ?', [$cleanPoUom])
                            ->first();
                    }

                    if ($matchedAlt) {
                        $poConvFactor = (float) $matchedAlt->conversion_qty;
                        $cleanPoUom = strtoupper($matchedAlt->uom_name);
                        $poUomId = $matchedAlt->id;
                    }
                } else {
                    $poUomId = $baseUomId;
                }
            }

            $safePoUom = $poConvFactor > 1 ? $cleanPoUom . ' (Isi: ' . $poConvFactor . ' ' . $baseUomName . ')' : $cleanPoUom;
            $sisaPoUom = (float)$item->qty_ordered - (float)($item->qty_received ?? 0);

            $item->sisa_po_uom = $sisaPoUom;
            $item->po_conv_factor = $poConvFactor;
            $item->base_uom_name = $baseUomName;
            $item->clean_po_uom = $cleanPoUom;
            $item->raw_po_uom = $safePoUom;
            $item->uom = $safePoUom;
            $item->uom_id_safe = $poUomId;

            $prItemDesc = null;
            $prItemId = $item->purchase_request_item_id;

            if (empty($prItemId) && !empty($po->purchase_request_id)) {
                $lacakPrItem = \Illuminate\Support\Facades\DB::table('purchase_request_items')
                                ->where('purchase_request_id', $po->purchase_request_id)
                                ->where('item_id', $item->item_id)
                                ->first();
                if ($lacakPrItem) $prItemId = $lacakPrItem->id;
            }

            if (!empty($prItemId)) {
                $prItemRow = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('id', $prItemId)->first();
                if ($prItemRow) {
                    $prItemDesc = $prItemRow->allocation_notes ?? $prItemRow->notes ?? $prItemRow->description ?? null;
                }
            }

            $isFromSmartRestock = false;
            $poNotes = strtolower($po->notes ?? '');
            $poDesc = strtolower($po->description ?? '');

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
                    $item->final_description = "Rincian Alokasi:\n- Dialokasikan untuk Gudang Utama";
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

    public function store(Request $request, $slug, \App\Services\SystemSettingService $settingService)
    {
        $this->healDatabaseColumns();

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
            'items.*.uom'          => 'nullable|string',
        ]);

        $warehouse = \App\Models\Warehouse::find($request->warehouse_id);
        if ($warehouse && $warehouse->is_frozen) {
            return back()->withInput()->with('error', "GAGAL: Gudang {$warehouse->name} sedang dalam status DIBEKUKAN (Stock Opname). Anda tidak dapat mengeluarkan barang dari gudang ini sampai proses audit selesai!");
        }

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
                    $targetWarehouseId = !empty($data['warehouse_id']) ? $data['warehouse_id'] : 1;

                    if ($inputQty > 0) {
                        $poItem = \App\Models\PurchaseOrderItem::findOrFail($itemId);
                        $masterItem = \App\Models\Item::with('uom', 'itemUoms')->findOrFail($data['item_id']);
                        $baseUomId = optional($masterItem)->uom_id;
                        $baseUomName = strtoupper(optional($masterItem->uom)->name ?? 'PCS');

                        // ========================================================
                        // 🔥 A. DETEKSI KONVERSI PO ASLI SECARA NATIVE
                        // ========================================================
                        $nativePoItem = \Illuminate\Support\Facades\DB::table('purchase_order_items')->where('id', $poItem->id)->first();
                        $poUomId = $nativePoItem->uom_id ?? null;

                        $poConvFactor = 1;
                        if ($poUomId && $poUomId != $baseUomId) {
                            $matchedAlt = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $poUomId)->first();
                            if ($matchedAlt) $poConvFactor = (float) $matchedAlt->conversion_qty;
                        } else {
                            $rawPoUom = $nativePoItem->uom ?? $baseUomName;
                            if (empty($rawPoUom) && !empty($nativePoItem->purchase_request_item_id)) {
                                $prRow = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('id', $nativePoItem->purchase_request_item_id)->first();
                                if ($prRow && Schema::hasColumn('purchase_request_items', 'uom')) $rawPoUom = $prRow->uom;
                            }

                            if (preg_match('/\(Isi:?\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                                $poConvFactor = (float) $matches[1];
                            }
                        }
                        $poConvFactorSafe = $poConvFactor > 0 ? $poConvFactor : 1;

                        // ========================================================
                        // 🔥 B. DETEKSI KONVERSI INPUT GR DARI USER
                        // ========================================================
                        $inputConvFactor = 1;
                        $inputId = $data['uom_id'] ?? null;
                        $inputUomStr = $data['uom'] ?? '';

                        if ($inputId && $inputId != $baseUomId) {
                            $uomDb = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $inputId)->first();
                            if ($uomDb) $inputConvFactor = (float) $uomDb->conversion_qty;
                        } elseif (preg_match('/\(Isi:?\s*([0-9.]+)/i', $inputUomStr, $matches)) {
                            $inputConvFactor = (float) $matches[1];
                        }

                        $finalUomString = trim(preg_replace('/ \[PO\]/i', '', $inputUomStr));

                        // ========================================================
                        // 🔥 C. MATEMATIKA KONVERSI MUTLAK
                        // ========================================================
                        $baseQtyReceived = $inputQty * $inputConvFactor;
                        $qtyYangMemotongPO = $baseQtyReceived / $poConvFactorSafe;

                        $poItem->qty_received = (float)($poItem->qty_received ?? 0) + $qtyYangMemotongPO;
                        $poItem->save();

                        // --- HITUNGAN HARGA & STOK ---
                        $hargaDariPO = (float) ($poItem->unit_price ?? 0);
                        $hargaDasarPerPiece = $hargaDariPO / $poConvFactorSafe;

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

                        \Illuminate\Support\Facades\DB::table('goods_receipt_items')->insert([
                            'goods_receipt_id'       => $gr->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id'                => $data['item_id'],
                            'qty_received'           => $inputQty,
                            'uom_id'                 => $data['uom_id'] ?? null,
                            'uom'                    => $finalUomString,
                            'condition_id'           => $data['condition_id'],
                            'notes'                  => $catatanAsli,
                            'created_at'             => now(),
                            'updated_at'             => now()
                        ]);

                        if ($masterItem->is_stockable ?? true) {
                            $globalBalanceBefore = (float) $masterItem->current_stock;
                            $namaSpesifik = strip_tags($poItem->description ?? $masterItem->name);

                            $noteMutasi = "Masuk: {$namaSpesifik} (Terima Fisik: {$inputQty} {$finalUomString})";

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

                $po->refresh();
                $allFullyReceived = true;
                foreach ($po->items as $item) {
                    if (round((float)($item->qty_received ?? 0), 4) < round((float)$item->qty_ordered, 4)) {
                        $allFullyReceived = false; break;
                    }
                }

                $newStatusSlug = $allFullyReceived ? 'fully_received' : 'partial_receipt';
                $statusTarget = \App\Models\Status::where('type', 'PO')
                                    ->whereIn('slug', [$newStatusSlug, ($newStatusSlug == 'partial_receipt' ? 'partial_received' : '')])
                                    ->orWhere('name', 'like', ($newStatusSlug == 'partial_receipt' ? '%Parsial%' : '%Selesai%'))
                                    ->first();

                if ($statusTarget) $po->update(['status_id' => $statusTarget->id]);

                return $grNumber;
            });

            return redirect()->route('gr.index')->with([
                'success'   => 'Penerimaan Barang berhasil disimpan (Matematika & UOM Terkalibrasi Sempurna!)',
                'print_url' => route('gr.print_vendor', $newGrNumber)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error Simpan GR: ' . $e->getMessage() . " di baris " . $e->getLine());
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 🔥 PERBAIKAN DISPLAY TAMPILAN SHOW DAN CETAK BPR (TAMPIL FULL) 🔥
    // =========================================================================
    private function getSafeUomDisplay($nativeItem, $baseUomName, $tableAsal = 'goods_receipt_items')
    {
        $uomId = $nativeItem->uom_id ?? null;

        if (!empty($uomId)) {
            $altDb = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $uomId)->first();
            if ($altDb) {
                $conv = (float) $altDb->conversion_qty;
                return $conv > 1 ? strtoupper($altDb->uom_name) . " (Isi: {$conv} {$baseUomName})" : strtoupper($altDb->uom_name);
            }
        }

        $rawUom = $nativeItem->uom ?? null;

        if (empty($rawUom) && $tableAsal === 'goods_receipt_items' && !empty($nativeItem->purchase_order_item_id)) {
            $poItem = \Illuminate\Support\Facades\DB::table('purchase_order_items')->where('id', $nativeItem->purchase_order_item_id)->first();
            if ($poItem && \Illuminate\Support\Facades\Schema::hasColumn('purchase_order_items', 'uom')) {
                $rawUom = $poItem->uom;
            }
        }

        if (empty($rawUom)) $rawUom = $baseUomName;

        if (is_string($rawUom) && str_starts_with(trim($rawUom), '{')) {
            $uomObj = json_decode($rawUom, true);
            if ($uomObj) {
                $uomObjLower = array_change_key_case($uomObj, CASE_LOWER);
                $rawUom = $uomObjLower['uom_name'] ?? $uomObjLower['name'] ?? $uomObjLower['code'] ?? $baseUomName;
            }
        }

        return trim(preg_replace('/ \[PO\]| \[PR\]| \[GR\]/i', '', $rawUom));
    }

    public function show($slug) {
        $this->healDatabaseColumns();

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
            $baseUomName = optional(optional($grItem->item)->uom)->name ?? 'PCS';
            $nativeGrItem = \Illuminate\Support\Facades\DB::table('goods_receipt_items')->where('id', $grItem->id)->first();

            $grItem->clean_uom_name = $this->getSafeUomDisplay($nativeGrItem, $baseUomName, 'goods_receipt_items');

            $whName = 'Gudang Utama / Default';

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

    public function printVendor($slug) {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $baseUomName = optional(optional($grItem->item)->uom)->name ?? 'PCS';
            $nativeGrItem = \Illuminate\Support\Facades\DB::table('goods_receipt_items')->where('id', $grItem->id)->first();
            $grItem->uom = $this->getSafeUomDisplay($nativeGrItem, $baseUomName, 'goods_receipt_items');

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

    public function printInternal($slug) {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $baseUomName = optional(optional($grItem->item)->uom)->name ?? 'PCS';
            $nativeGrItem = \Illuminate\Support\Facades\DB::table('goods_receipt_items')->where('id', $grItem->id)->first();
            $grItem->uom = $this->getSafeUomDisplay($nativeGrItem, $baseUomName, 'goods_receipt_items');

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

    public function print($slug) {
        $gr = \App\Models\GoodsReceipt::with([
            'items.item.itemUoms', 'items.purchaseOrderItem', 'purchaseOrder.vendor',
            'purchaseOrder.company', 'creator', 'items.condition'
        ])->where('gr_number', $slug)->firstOrFail();

        foreach ($gr->items as $grItem) {
            $baseUomName = optional(optional($grItem->item)->uom)->name ?? 'PCS';
            $nativeGrItem = \Illuminate\Support\Facades\DB::table('goods_receipt_items')->where('id', $grItem->id)->first();
            $grItem->uom = $this->getSafeUomDisplay($nativeGrItem, $baseUomName, 'goods_receipt_items');

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

    public function printLabels($slug) {
        $gr = \App\Models\GoodsReceipt::with([
            'po.company',
            'items.item',
            'items.purchaseOrderItem'
        ])->where('gr_number', $slug)->firstOrFail();

        $labelItems = $gr->items->filter(function ($grItem) {
            return $grItem->item && $grItem->item->is_trackable;
        });

        if ($labelItems->isEmpty()) {
            return back()->with('error', 'Tidak ada Inventaris yang perlu dicetak label SN-nya pada dokumen GR ini.');
        }

        return view('gr.print_labels', compact('gr', 'labelItems'));
    }
}
