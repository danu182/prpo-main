<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReturnToVendor;
use App\Models\ReturnToVendorItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrderItem;
use App\Models\Item;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use App\Models\FixedAsset;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnToVendorController extends Controller
{
    private function generateRtvNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company && $company->code ? strtoupper($company->code) : 'UMUM';
        $year = date('Y');
        $month = date('m');

        $prefix = "RTV-{$companyCode}-{$year}-{$month}-";

        $lastRtv = ReturnToVendor::where('rtv_number', 'LIKE', "{$prefix}%")
                    ->orderBy('id', 'desc')->lockForUpdate()->first();

        $nextSequence = $lastRtv ? ((int) substr($lastRtv->rtv_number, -4)) + 1 : 1;
        return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $rtvs = ReturnToVendor::with(['vendor', 'goodsReceipt.po', 'returner'])
            ->when($search, function ($query) use ($search) {
                $query->where('rtv_number', 'like', "%{$search}%")
                      ->orWhere('delivery_note_number', 'like', "%{$search}%")
                      ->orWhereHas('vendor', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()->paginate(10);

        return view('rtv.index', compact('rtvs'));
    }

    public function show($slug)
    {
        $rtv = ReturnToVendor::with([
            'vendor',
            'goodsReceipt.po.company',
            'goodsReceipt.warehouse',
            'returner',
            'items.item',
            'attachments'
        ])->where('rtv_number', $slug)->firstOrFail();

        return view('rtv.show', compact('rtv'));
    }

    public function create($slug)
    {
        $gr = GoodsReceipt::with(['items.item.itemUoms', 'items.purchaseOrderItem', 'po.vendor', 'po.company'])
                ->where('gr_number', $slug)->firstOrFail();

        $reasons = \App\Models\ReturnReason::where('is_active', true)->orderBy('name')->get();

        $returnableItems = $gr->items->filter(function ($item) use ($gr) {
            $masterItem = $item->item;
            $baseUomId = optional($masterItem)->uom_id;
            $baseUomName = strtoupper(optional($masterItem->uom)->name ?? 'PCS');

            // 🔥 A. CARI KONVERSI GR ASLI (DENGAN KUNCI ITEM_ID AGAR TIDAK TABRAKAN) 🔥
            $grConvRate = 1;
            $rawGrUom = $item->getRawOriginal('uom') ?: $item->uom ?: $baseUomName;

            if (!empty($item->uom_id) && $item->uom_id != $baseUomId) {
                $uomDb = collect(optional($masterItem)->itemUoms)->where('id', $item->uom_id)->first();
                if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
            } elseif (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawGrUom, $matches)) {
                $grConvRate = (float) $matches[1];
            }

            $sisaKuotaGR = (float) $item->qty_received - (float) ($item->qty_returned ?? 0);
            $maxReturnable = max(0, $sisaKuotaGR);

            // TENTUKAN WAREHOUSE ID
            $warehouseIdForRtv = null;
            $poItem = $item->purchaseOrderItem;
            if ($poItem && $poItem->purchase_request_item_id) {
                $prItem = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('id', $poItem->purchase_request_item_id)->first();
                if ($prItem && $prItem->allocation_notes) {
                    if (preg_match('/untuk\s+(Gudang.*?)(?:\n|\r|,|$)/i', $prItem->allocation_notes, $matches)) {
                        $whName = trim($matches[1]);
                        $whDb = \Illuminate\Support\Facades\DB::table('warehouses')->where('name', $whName)->first();
                        if ($whDb) $warehouseIdForRtv = $whDb->id;
                    }
                }
            }
            if (!$warehouseIdForRtv) {
                try {
                    $mov = \Illuminate\Support\Facades\DB::table('inventory_movements')
                        ->leftJoin('inventory_stocks', 'inventory_movements.inventory_stock_id', '=', 'inventory_stocks.id')
                        ->where(function($q) use ($gr) {
                            $q->where('inventory_movements.reference_number', $gr->gr_number)->orWhere('inventory_movements.reference_number', (string) $gr->id);
                        })->where('inventory_stocks.item_id', $item->item_id)->select('inventory_movements.warehouse_id')->first();
                    if ($mov && $mov->warehouse_id) $warehouseIdForRtv = $mov->warehouse_id;
                } catch (\Exception $e) {}
            }

            $item->detected_warehouse_id = $warehouseIdForRtv;
            $tempSnList = [];

            if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                $snQuery = \DB::table('item_serials')
                    ->where('item_id', $item->item_id)
                    ->where('goods_receipt_id', $gr->id)
                    ->where('status', 'AVAILABLE');

                if ($warehouseIdForRtv) $snQuery->where('warehouse_id', $warehouseIdForRtv);

                $availableSerials = $snQuery->pluck('serial_number')->toArray();

                if (!empty($availableSerials)) {
                    $tempSnList = $availableSerials;
                    $maxReturnable = min($sisaKuotaGR, (count($availableSerials) / $grConvRate));
                } else {
                    $maxReturnable = 0;
                }

            } elseif (isset($masterItem->is_stockable) && $masterItem->is_stockable) {
                $stokGudangQuery = \App\Models\InventoryStock::where('item_id', $item->item_id);
                if ($warehouseIdForRtv) $stokGudangQuery->where('warehouse_id', $warehouseIdForRtv);

                $stokGudang_Base = $stokGudangQuery->sum('stock_qty');
                $stokGudang_GR = $stokGudang_Base / $grConvRate;
                $maxReturnable = min($sisaKuotaGR, $stokGudang_GR);
            }

            $item->available_sn_list = $tempSnList;
            $item->max_returnable = max(0, $maxReturnable);
            $item->gr_conv_rate = $grConvRate;
            $item->gr_uom_text = $rawGrUom;

            return $item->max_returnable > 0;
        });

        if ($returnableItems->isEmpty()) {
            return redirect()->route('gr.show', $gr->gr_number)->with('error', 'Semua barang dari GR ini sudah diretur, ATAU stok/SN-nya sudah tidak ada di gudang (mungkin sedang dipinjam).');
        }

        return view('rtv.create', compact('gr', 'reasons', 'returnableItems'));
    }

    public function store(Request $request, $slug)
    {
        $request->validate([
            'return_date'          => 'required|date',
            'items'                => 'required|array',
            'items.*.qty_returned' => 'required|numeric|min:0',
            'attachments'          => 'nullable|array',
            'attachments.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            $rtvNumberToPrint = DB::transaction(function () use ($request, $slug) {
                $gr = GoodsReceipt::with('po')->where('gr_number', $slug)->firstOrFail();
                $companyId = $gr->po->bill_to_company_id;

                $rtv = ReturnToVendor::create([
                    'rtv_number'           => $this->generateRtvNumber($companyId),
                    'goods_receipt_id'     => $gr->id,
                    'vendor_id'            => $gr->po->vendor_id,
                    'return_date'          => $request->return_date,
                    'delivery_note_number' => $request->delivery_note_number,
                    'returned_by'          => auth()->id(),
                    'notes'                => $request->notes,
                ]);

                if ($request->hasFile('attachments')) {
                    $safeRtvNumber = str_replace('/', '-', $rtv->rtv_number);
                    $settingPath = \DB::table('system_settings')->where('setting_key', 'path_rtv_attachment')->value('setting_value');
                    $basePath = $settingPath ? $settingPath : 'attachments/rtv';
                    $targetFolder = $basePath . '/' . $safeRtvNumber;
                    $flatFiles = \Illuminate\Support\Arr::flatten([$request->file('attachments')]);

                    foreach ($flatFiles as $file) {
                        if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                            $originalName = $file->getClientOriginalName();
                            $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                            $path = $file->storeAs($targetFolder, $filename, 'public');

                            \DB::table('return_to_vendor_attachments')->insert([
                                'return_to_vendor_id' => $rtv->id,
                                'file_name'           => $originalName,
                                'file_path'           => str_replace('\\', '/', $path),
                                'created_at'          => now(),
                                'updated_at'          => now()
                            ]);
                        }
                    }
                }

                $totalQtyReturnedInThisTransaction = 0;

                foreach ($request->items as $grItemId => $data) {
                    $inputQty = (float) ($data['qty_returned'] ?? 0);
                    if ($inputQty <= 0) continue;

                    $totalQtyReturnedInThisTransaction += $inputQty;
                    $grItem = GoodsReceiptItem::findOrFail($grItemId);
                    $poItem = PurchaseOrderItem::findOrFail($grItem->purchase_order_item_id);
                    $masterItem = Item::with('uom', 'itemUoms')->findOrFail($grItem->item_id);
                    $baseUomId = optional($masterItem)->uom_id;
                    $baseUomName = strtoupper(optional($masterItem->uom)->name ?? 'PCS');

                    $warehouseIdForRtv = $data['warehouse_id'] ?? null;

                    // =========================================================
                    // 🔥 LOGIKA KONVERSI MUTLAK & ANTI-TABRAKAN LINTAS ITEM 🔥
                    // =========================================================

                    // A. CARI KONVERSI PO ASLI
                    $poConvFactorSafe = 1;
                    $rawPoUom = $poItem->getRawOriginal('uom') ?: $poItem->uom ?: $baseUomName;
                    if (!empty($poItem->uom_id) && $poItem->uom_id != $baseUomId) {
                        // KUNCI: Tambahkan ->where('item_id', $masterItem->id)
                        $uomDb = \App\Models\ItemUom::where('id', $poItem->uom_id)->where('item_id', $masterItem->id)->first();
                        if ($uomDb) $poConvFactorSafe = (float) $uomDb->conversion_qty;
                    } elseif (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                        $poConvFactorSafe = (float) $matches[1];
                    }

                    // B. CARI KONVERSI GR ASLI
                    $grConvFactorSafe = 1;
                    $rawGrUom = $grItem->getRawOriginal('uom') ?: $grItem->uom ?: $baseUomName;
                    if (!empty($grItem->uom_id) && $grItem->uom_id != $baseUomId) {
                        // KUNCI: Tambahkan ->where('item_id', $masterItem->id)
                        $uomDb = \App\Models\ItemUom::where('id', $grItem->uom_id)->where('item_id', $masterItem->id)->first();
                        if ($uomDb) $grConvFactorSafe = (float) $uomDb->conversion_qty;
                    } elseif (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawGrUom, $matches)) {
                        $grConvFactorSafe = (float) $matches[1];
                    }

                    // C. CARI KONVERSI INPUT RETUR
                    $inputConvFactor = 1;
                    $inputUomStr = $data['uom'] ?? '';

                    if (!empty($data['uom_id']) && $data['uom_id'] != $baseUomId) {
                        // KUNCI: Tambahkan ->where('item_id', $masterItem->id)
                        $uomDb = \App\Models\ItemUom::where('id', $data['uom_id'])->where('item_id', $masterItem->id)->first();
                        if ($uomDb) $inputConvFactor = (float) $uomDb->conversion_qty;
                    } elseif (preg_match('/\(Isi:\s*([0-9.]+)/i', $inputUomStr, $matches)) {
                        $inputConvFactor = (float) $matches[1];
                    } else {
                        $cleanInput = trim(preg_replace('/ \[GR\]|\(Ecer\)/i', '', $inputUomStr));
                        if (strtoupper($cleanInput) === strtoupper($baseUomName)) {
                            $inputConvFactor = 1;
                        } else {
                            $inputConvFactor = $grConvFactorSafe;
                        }
                    }

                    // D. KALKULASI ABSOLUT DALAM ECERAN (PIECES)
                    $baseQtyReturned = $inputQty * $inputConvFactor;
                    $baseQtyReceivedSoFar = $grItem->qty_received * $grConvFactorSafe;
                    $baseQtyReturnedSoFar = ($grItem->qty_returned ?? 0) * $grConvFactorSafe;
                    $sisaBaseKuotaGR = round($baseQtyReceivedSoFar - $baseQtyReturnedSoFar, 4);

                    if (round($baseQtyReturned, 4) > $sisaBaseKuotaGR) {
                        throw new \Exception("Jumlah retur ({$baseQtyReturned} {$baseUomName}) melebihi sisa penerimaan dokumen GR!");
                    }

                    $reasonName = 'Alasan Lainnya';
                    if (!empty($data['return_reason_id'])) {
                        $reasonModel = \App\Models\ReturnReason::find($data['return_reason_id']);
                        if ($reasonModel) $reasonName = $reasonModel->name;
                    }

                    $snRetur = $data['sn'] ?? [];
                    $snStringRetur = '';
                    $isSnRequired = (isset($masterItem->is_trackable) && $masterItem->is_trackable);
                    $hasCheckedSn = !empty($snRetur);

                    if ($isSnRequired && $hasCheckedSn) {
                        $jumlahSnCentang = count($snRetur);
                        $jumlahHarusnya = (int)$baseQtyReturned;

                        if ($jumlahSnCentang !== $jumlahHarusnya) {
                            throw new \Exception("Jumlah centangan Serial Number ({$jumlahSnCentang}) tidak sesuai dengan Qty Retur ({$jumlahHarusnya}) untuk barang {$masterItem->name}!");
                        }

                        \DB::table('item_serials')
                            ->whereIn('serial_number', $snRetur)
                            ->update([
                                'status'              => 'RETURNED',
                                'return_to_vendor_id' => $rtv->id,
                                'updated_at'          => now()
                            ]);

                        $snStringRetur = implode(', ', $snRetur);
                    }

                    $catatanKonversi = $reasonName;
                    if ($snStringRetur) $catatanKonversi .= " | SN: {$snStringRetur}";

                    // =========================================================================
                    // E. PEMOTONGAN STOK GUDANG MENGGUNAKAN FILTER WAREHOUSE
                    // =========================================================================
                    if ($masterItem->is_stockable ?? true) {
                        $stockQuery = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                                                ->where('stock_qty', '>', 0)
                                                                ->orderBy('created_at', 'asc')->lockForUpdate();

                        if ($warehouseIdForRtv) {
                            $stockQuery->where('warehouse_id', $warehouseIdForRtv);
                        }

                        $availableStocks = $stockQuery->get();
                        $totalAvailable = $availableStocks->sum('stock_qty');

                        if (round($baseQtyReturned, 4) > round($totalAvailable, 4)) {
                            throw new \Exception("Gagal! Stok fisik '{$masterItem->name}' di gudang tersisa {$totalAvailable} {$baseUomName}, tidak cukup untuk diretur ke vendor.");
                        }

                        $qtySisaRetur = $baseQtyReturned;
                        $saldoTotalSaatIni = (float) $masterItem->current_stock;

                        foreach ($availableStocks as $stockRow) {
                            if ($qtySisaRetur <= 0) break;
                            $potong = min($stockRow->stock_qty, $qtySisaRetur);

                            $balanceBefore = $saldoTotalSaatIni;
                            $balanceAfter = $balanceBefore - $potong;
                            $saldoTotalSaatIni = $balanceAfter;

                            $stockRow->decrement('stock_qty', $potong);
                            $qtySisaRetur -= $potong;

                            $mutasiNoteExt = "";
                            if ($snStringRetur) $mutasiNoteExt = " [SN: {$snStringRetur}]";

                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $stockRow->warehouse_id,
                                'type'             => 'OUT',
                                'qty'              => $potong,
                                'balance_before'   => $balanceBefore,
                                'balance_after'    => $balanceAfter,
                                'reference_number' => $rtv->rtv_number,
                                'notes'            => "Retur ke Vendor. Ref GR: {$gr->gr_number}. Alasan: {$reasonName}.{$mutasiNoteExt}",
                                'created_by'       => auth()->id(),
                            ]);
                        }
                        $masterItem->update(['current_stock' => $saldoTotalSaatIni]);
                    }

                    $finalUomString = trim(preg_replace('/ \[GR\]|\(Ecer\)/i', '', $inputUomStr));
                    if ($inputConvFactor > 1 && !preg_match('/\(Isi:/i', $finalUomString)) {
                        $finalUomString .= ' (Isi: ' . (float)$inputConvFactor . ' ' . $baseUomName . ')';
                    }

                    ReturnToVendorItem::create([
                        'return_to_vendor_id'    => $rtv->id,
                        'goods_receipt_item_id'  => $grItem->id,
                        'purchase_order_item_id' => $poItem->id,
                        'item_id'                => $masterItem->id,
                        'qty_returned'           => $inputQty,
                        'uom_id'                 => $data['uom_id'] ?? null,
                        'uom'                    => $finalUomString,
                        'return_reason'          => $catatanKonversi,
                    ]);

                    // =========================================================================
                    // 🔥 F. MENGEMBALIKAN (MEMBUKA) KUOTA PO & MENAMBAH RETUR DI GR 🔥
                    // Matematika: Eceran dikembalikan lalu dibagi dengan porsi konversi masing2
                    // =========================================================================

                    // 1. Tambah qty_returned di form GR
                    $grItem->qty_returned = (float)($grItem->qty_returned ?? 0) + ($baseQtyReturned / $grConvFactorSafe);
                    $grItem->save();

                    // 2. Kurangi qty_received di form PO (Membuka kembali sisa jatah terima PO)
                    $poItem->qty_received = max(0, (float)($poItem->qty_received ?? 0) - ($baseQtyReturned / $poConvFactorSafe));
                    $poItem->save();
                }

                if ($totalQtyReturnedInThisTransaction == 0) throw new \Exception("Isi minimal 1 qty barang yang diretur.");

                // UPDATE STATUS PO KEMBALI KE PARTIAL JIKA PERLU
                $po = $gr->po;
                $po->refresh();
                $allFullyReceived = true;
                foreach ($po->items as $item) {
                    if (round($item->qty_received ?? 0, 4) < round($item->qty_ordered, 4)) {
                        $allFullyReceived = false; break;
                    }
                }

                if (!$allFullyReceived && optional($po->status)->slug === 'fully_received') {
                    $statusPartial = \App\Models\Status::where('type', 'PO')->where('slug', 'partial_receipt')->first();
                    if ($statusPartial) {
                        $po->update(['status_id' => $statusPartial->id]);
                        \App\Models\PurchaseOrderHistory::create([
                            'purchase_order_id' => $po->id, 'user_id' => auth()->id(), 'action' => 'RETURN TO VENDOR',
                            'note' => "Terdapat barang diretur (RTV: {$rtv->rtv_number}). Status PO otomatis mundur ke Parsial."
                        ]);
                    }
                }

                return $rtv->rtv_number;
            });

            return redirect()->route('rtv.index')->with(['success' => 'Dokumen RTV & Lampiran berhasil disimpan!', 'print_url' => route('rtv.print', $rtvNumberToPrint)]);
        } catch (\Exception $e) {
            \Log::error('Error Simpan RTV: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memproses Retur: ' . $e->getMessage());
        }
    }


    public function print($slug)
    {
        $rtv = ReturnToVendor::with([
            'vendor',
            'goodsReceipt.po.company',
            'returner',
            'items.item',
            'items.purchaseOrderItem'
        ])->where('rtv_number', $slug)->firstOrFail();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rtv.print', compact('rtv'))
                ->setPaper('A4', 'portrait');

        $namaFile = str_replace('/', '_', $rtv->rtv_number);
        return $pdf->stream('Bukti_Retur_Vendor_' . $namaFile . '.pdf');
    }
}
