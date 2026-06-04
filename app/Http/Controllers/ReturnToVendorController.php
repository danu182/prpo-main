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
            'returner',
            'items.item',
            'attachments' // Tarik lampiran RTV
        ])->where('rtv_number', $slug)->firstOrFail();

        return view('rtv.show', compact('rtv'));
    }

    public function create($slug)
    {
        $gr = GoodsReceipt::with(['items.item.itemUoms', 'items.purchaseOrderItem', 'po.vendor', 'po.company'])
                ->where('gr_number', $slug)->firstOrFail();

        $reasons = \App\Models\ReturnReason::where('is_active', true)->orderBy('name')->get();

        $returnableItems = $gr->items->filter(function ($item) use ($gr) {
            $poItem = $item->purchaseOrderItem;
            $masterItem = $item->item;

            $rawPoUom = is_string($poItem->uom) ? $poItem->uom : (optional($poItem->uom)->name ?? $poItem->getRawOriginal('uom') ?? 'PCS');
            $cleanPoUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $rawPoUom));
            
            $poConvRate = 1;
            if (preg_match('/\(Isi:\s*([0-9.]+)\)/i', $rawPoUom, $matches)) $poConvRate = (float) $matches[1];
            else {
                $poUomModel = collect(optional($masterItem)->itemUoms)->where('uom_name', $cleanPoUom)->first();
                if ($poUomModel) $poConvRate = (float) $poUomModel->conversion_qty;
            }

            $sisaKuotaGR = $item->qty_received - ($item->qty_returned ?? 0);
            $maxReturnable = $sisaKuotaGR; // By default

            if ($masterItem && $masterItem->is_stockable) {
                $stokGudang_Base = \App\Models\InventoryStock::where('item_id', $item->item_id)->sum('stock_qty');
                $stokGudang_PO = $stokGudang_Base / $poConvRate;
                $maxReturnable = min($sisaKuotaGR, $stokGudang_PO);
            }

            $item->max_returnable = $maxReturnable;

            // 🔥 TARIK DATA SERIAL NUMBER (CARA BARU BACA LANGSUNG DARI DATABASE SN) 🔥
            $item->available_sn_list = []; // Inisialisasi awal (Kosong)
            
            if ($masterItem && ($masterItem->is_trackable || $masterItem->is_asset)) {
                // Cari SN di database yang dibuat pada waktu yang bersamaan dengan GR ini
                $availableSerials = \DB::table('item_serials')
                    ->where('item_id', $item->item_id)
                    ->where('status', 'AVAILABLE')
                    ->whereBetween('created_at', [
                        $gr->created_at->copy()->subMinutes(5),
                        $gr->created_at->copy()->addMinutes(5)
                    ])
                    ->pluck('serial_number')
                    ->toArray();

                // Jika SN-nya ketemu di database, munculkan checkbox-nya
                if (!empty($availableSerials)) {
                    $item->available_sn_list = $availableSerials;
                    $item->max_returnable = min($maxReturnable, (count($availableSerials) / $poConvRate));
                }
            }

            return $item->max_returnable > 0;
        });

        if ($returnableItems->isEmpty()) {
            return redirect()->route('gr.show', $gr->gr_number)->with('error', 'Semua barang dari GR ini sudah diretur, ATAU stok/Serial Number-nya sudah tidak ada di gudang.');
        }

        return view('rtv.create', compact('gr', 'reasons', 'returnableItems'));
    }

    // public function store(Request $request, $slug, \App\Services\SystemSettingService $settingService)
    // {
    //     $request->validate([
    //         'return_date'          => 'required|date',
    //         'items'                => 'required|array',
    //         'items.*.qty_returned' => 'required|numeric|min:0',
    //         'attachments'          => 'nullable|array',
    //         'attachments.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
    //     ]);

    //     try {
    //         $rtvNumberToPrint = DB::transaction(function () use ($request, $slug, $settingService) {
    //             $gr = GoodsReceipt::with('po')->where('gr_number', $slug)->firstOrFail();
    //             $companyId = $gr->po->bill_to_company_id;

    //             $rtv = ReturnToVendor::create([
    //                 'rtv_number'           => $this->generateRtvNumber($companyId),
    //                 'goods_receipt_id'     => $gr->id,
    //                 'vendor_id'            => $gr->po->vendor_id,
    //                 'return_date'          => $request->return_date,
    //                 'delivery_note_number' => $request->delivery_note_number,
    //                 'returned_by'          => auth()->id(),
    //                 'notes'                => $request->notes,
    //             ]);

    //             // ==============================================================
    //             // 🔥 PROSES UPLOAD MULTI-LAMPIRAN RTV (KONSEP SERAGAM DENGAN PR/PO/GR) 🔥
    //             // ==============================================================
    //             if ($request->hasFile('attachments')) {
    //                 $safeRtvNumber = str_replace('/', '-', $rtv->rtv_number);
                    
    //                 // 1. Ambil path folder utama dari tabel system_settings (Sama seperti konsep PR/PO/GR)
    //                 $settingPath = \DB::table('system_settings')
    //                                  ->where('setting_key', 'path_rtv_attachment')
    //                                  ->value('setting_value');
                    
    //                 // 2. Beri fallback (jaring pengaman) jika value di database ternyata kosong
    //                 $basePath = $settingPath ? $settingPath : 'attachments/rtvs';
                    
    //                 // 3. Tentukan folder target spesifik per nomor dokumen RTV
    //                 $targetFolder = $basePath . '/' . $safeRtvNumber;
                    
    //                 // Ratakan array file lampiran
    //                 $flatFiles = \Illuminate\Support\Arr::flatten([$request->file('attachments')]);

    //                 foreach ($flatFiles as $file) {
    //                     if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
    //                         $originalName = $file->getClientOriginalName();
                            
    //                         // Amankan nama file dengan timestamp + id unik agar tidak saling timpa
    //                         $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                            
    //                         // Simpan file ke disk 'public' sesuai dengan path dari system_settings
    //                         $path = $file->storeAs($targetFolder, $filename, 'public');
                            
    //                         // 4. Catat jejak file ke tabel anak return_to_vendor_attachments
    //                         \DB::table('return_to_vendor_attachments')->insert([
    //                             'return_to_vendor_id' => $rtv->id,
    //                             'file_name'           => $originalName,
    //                             'file_path'           => str_replace('\\', '/', $path), // Seragamkan slashes ke arah kanan
    //                             'created_at'          => now(),
    //                             'updated_at'          => now()
    //                         ]);
    //                     }
    //                 }
    //             }
    //             $totalQtyReturnedInThisTransaction = 0;

    //             foreach ($request->items as $grItemId => $data) {
    //                 $inputQty = (float) ($data['qty_returned'] ?? 0);
    //                 if ($inputQty > 0) {
    //                     $totalQtyReturnedInThisTransaction += $inputQty;
    //                     $grItem = GoodsReceiptItem::findOrFail($grItemId);
    //                     $poItem = PurchaseOrderItem::findOrFail($grItem->purchase_order_item_id);
    //                     $masterItem = Item::with('uom', 'itemUoms')->findOrFail($grItem->item_id);
    //                     $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

    //                     $rawPoUom = is_string($poItem->uom) ? $poItem->uom : (optional($poItem->uom)->name ?? $poItem->getRawOriginal('uom') ?? 'PCS');
    //                     $cleanPoUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $rawPoUom));

    //                     $poConvFactor = 1;
    //                     if (preg_match('/\(Isi:\s*([0-9.]+)\)/i', $rawPoUom, $matches)) $poConvFactor = (float) $matches[1];
    //                     else {
    //                         $poUomData = \App\Models\ItemUom::where('item_id', $masterItem->id)->where('uom_name', $cleanPoUom)->first();
    //                         if ($poUomData) $poConvFactor = (float) $poUomData->conversion_qty;
    //                     }

    //                     $inputUom = $data['uom'] ?? $rawPoUom; 
    //                     $cleanInputUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $inputUom));
                        
    //                     $inputConvFactor = 1;
    //                     if (preg_match('/\(Isi:\s*([0-9.]+)\)/i', $inputUom, $matches)) $inputConvFactor = (float) $matches[1];
    //                     else {
    //                         $inputUomData = \App\Models\ItemUom::where('item_id', $masterItem->id)->where('uom_name', $cleanInputUom)->first();
    //                         if ($inputUomData) $inputConvFactor = (float) $inputUomData->conversion_qty;
    //                     }

    //                     $baseQtyReturned = $inputQty * $inputConvFactor; 
    //                     $baseQtyReceivedSoFar = ($grItem->qty_received * $poConvFactor); 
    //                     $baseQtyReturnedSoFar = ($grItem->qty_returned ?? 0) * $poConvFactor;
    //                     $sisaBaseKuotaGR = round($baseQtyReceivedSoFar - $baseQtyReturnedSoFar, 4);

    //                     if (round($baseQtyReturned, 4) > $sisaBaseKuotaGR) {
    //                         throw new \Exception("Jumlah retur ({$baseQtyReturned} {$baseUomName}) melebihi sisa penerimaan dokumen GR!");
    //                     }

    //                     $reasonName = 'Alasan Lainnya';
    //                     if (!empty($data['return_reason_id'])) {
    //                         $reasonModel = \App\Models\ReturnReason::find($data['return_reason_id']);
    //                         if ($reasonModel) $reasonName = $reasonModel->name;
    //                     }

    //                     // 🔥 PROSES RETUR SERIAL NUMBER (CARA BARU) 🔥
    //                     $snRetur = $data['sn'] ?? []; // Array dari checkbox yang dicentang
    //                     $snStringRetur = '';
    //                     $isSnRequired = ($masterItem->is_asset || $masterItem->is_trackable);
                        
    //                     // Jika ada checkbox yang dicentang dari tampilan, berarti ini retur dengan SN!
    //                     $hasCheckedSn = !empty($snRetur);

    //                     if ($isSnRequired && $hasCheckedSn) {
    //                         $jumlahSnCentang = count($snRetur);
    //                         $jumlahHarusnya = (int)$baseQtyReturned;

    //                         if ($jumlahSnCentang !== $jumlahHarusnya) {
    //                             throw new \Exception("Jumlah centangan Serial Number ({$jumlahSnCentang}) tidak sesuai dengan Qty Retur ({$jumlahHarusnya}) untuk barang {$masterItem->name}!");
    //                         }

    //                         // Update tabel item_serials jadi RETURNED
    //                         \DB::table('item_serials')
    //                             ->whereIn('serial_number', $snRetur)
    //                             ->update(['status' => 'RETURNED', 'updated_at' => now()]);

    //                         $snStringRetur = implode(' | ', $snRetur);
    //                     }
                        
    //                     $catatanKonversi = "Diretur: {$inputQty} {$inputUom} (= {$baseQtyReturned} {$baseUomName}). Alasan: {$reasonName}";
    //                     if ($snStringRetur) $catatanKonversi .= " [SN Diretur: {$snStringRetur}]";

    //                     // PEMOTONGAN STOK GLOBAL DARI BATCH
    //                     if ($masterItem->is_stockable) {
    //                         $availableStocks = \App\Models\InventoryStock::where('item_id', $masterItem->id)
    //                                             ->where('stock_qty', '>', 0)
    //                                             ->orderBy('created_at', 'asc')->lockForUpdate()->get();

    //                         $totalAvailable = $availableStocks->sum('stock_qty');

    //                         if (round($baseQtyReturned, 4) > round($totalAvailable, 4)) {
    //                             throw new \Exception("Gagal! Stok fisik '{$masterItem->name}' di sistem tersisa {$totalAvailable} {$baseUomName}, tidak cukup untuk retur.");
    //                         }

    //                         $qtySisaRetur = $baseQtyReturned; 
    //                         foreach ($availableStocks as $stockRow) {
    //                             if ($qtySisaRetur <= 0) break;
    //                             $potong = min($stockRow->stock_qty, $qtySisaRetur);
    //                             $balanceBefore = (float) $stockRow->stock_qty;
    //                             $stockRow->decrement('stock_qty', $potong);
    //                             $balanceAfter = $balanceBefore - $potong;
    //                             $qtySisaRetur -= $potong;

    //                             StockMutation::create([
    //                                 'item_id'          => $masterItem->id,
    //                                 'warehouse_id'     => $stockRow->warehouse_id,
    //                                 'type'             => 'OUT',
    //                                 'qty'              => $potong,
    //                                 'balance_before'   => $balanceBefore,
    //                                 'balance_after'    => $balanceAfter,
    //                                 'reference_number' => $rtv->rtv_number,
    //                                 'notes'            => "Retur ke Vendor. {$catatanKonversi}",
    //                                 'created_by'       => auth()->id(),
    //                             ]);
    //                         }
    //                         $masterItem->decrement('current_stock', $baseQtyReturned);
    //                     }

    //                     // JIKA ASET, UBAH STATUS FIXED ASSET (Double Protection)
    //                     if ($masterItem->is_asset && !empty($snRetur)) {
                            
    //                         // Cari ID status Retur di Master Status, jika tidak ketemu fallback ke 1
    //                         $statusRetur = \App\Models\Status::where('type', 'AST')
    //                                          ->where(function($q) {
    //                                              $q->where('slug', 'returned')
    //                                                ->orWhere('name', 'like', '%Return%');
    //                                          })->first();
                                             
    //                         $statusIdTarget = $statusRetur ? $statusRetur->id : 1;

    //                         \App\Models\FixedAsset::whereIn('serial_number', $snRetur)->update([
    //                             'status_id' => $statusIdTarget, // Memakai status_id (Sesuai database Komandan)
    //                             'notes'     => 'Dikembalikan ke vendor via RTV: ' . $rtv->rtv_number
    //                         ]);
    //                     }

    //                     // Simpan Item RTV & Update GR/PO
    //                     ReturnToVendorItem::create([
    //                         'return_to_vendor_id'    => $rtv->id,
    //                         'goods_receipt_item_id'  => $grItem->id,
    //                         'purchase_order_item_id' => $poItem->id,
    //                         'item_id'                => $masterItem->id,
    //                         'qty_returned'           => $inputQty, 
    //                         'return_reason'          => $catatanKonversi,
    //                     ]);

    //                     $poItem->decrement('qty_received', ($baseQtyReturned / $poConvFactor));
    //                     $grItem->increment('qty_returned', ($baseQtyReturned / $poConvFactor));
    //                 }
    //             }

    //             if ($totalQtyReturnedInThisTransaction == 0) throw new \Exception("Isi minimal 1 qty barang yang diretur.");

    //             $po = $gr->po;
    //             $po->refresh();
    //             $allFullyReceived = true;
    //             foreach ($po->items as $item) {
    //                 if (round($item->qty_received ?? 0, 4) < round($item->qty_ordered, 4)) {
    //                     $allFullyReceived = false; break;
    //                 }
    //             }

    //             if (!$allFullyReceived && optional($po->status)->slug === 'fully_received') {
    //                 $statusPartial = Status::where('type', 'PO')->where('slug', 'partial_receipt')->first();
    //                 if ($statusPartial) {
    //                     $po->update(['status_id' => $statusPartial->id]);
    //                     \App\Models\PurchaseOrderHistory::create([
    //                         'purchase_order_id' => $po->id, 'user_id' => auth()->id(), 'action' => 'RETURN TO VENDOR',
    //                         'note' => "Terdapat barang diretur (RTV: {$rtv->rtv_number}). Status PO dikembalikan ke Parsial."
    //                     ]);
    //                 }
    //             }
    //             return $rtv->rtv_number; 
    //         });

    //         return redirect()->route('rtv.index')->with(['success' => 'Dokumen RTV & Lampiran berhasil disimpan!', 'print_url' => route('rtv.print', $rtvNumberToPrint)]);
    //     } catch (\Exception $e) {
    //         \Log::error('Error Simpan RTV: ' . $e->getMessage());
    //         return back()->withInput()->with('error', 'Gagal memproses Retur: ' . $e->getMessage());
    //     }
    // }



    // ========================================================
    // STORE RTV (DENGAN SURAT KEMATIAN SN & LAMPIRAN DINAMIS)
    // ========================================================
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

                // 🔥 UPLOAD BANYAK LAMPIRAN DENGAN PATH DINAMIS 🔥
                if ($request->hasFile('attachments')) {
                    $safeRtvNumber = str_replace('/', '-', $rtv->rtv_number);
                    
                    $settingPath = \DB::table('system_settings')
                                     ->where('setting_key', 'path_rtv_attachment')
                                     ->value('setting_value');
                    
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
                    if ($inputQty > 0) {
                        $totalQtyReturnedInThisTransaction += $inputQty;
                        $grItem = GoodsReceiptItem::findOrFail($grItemId);
                        $poItem = PurchaseOrderItem::findOrFail($grItem->purchase_order_item_id);
                        $masterItem = Item::with('uom', 'itemUoms')->findOrFail($grItem->item_id);
                        $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                        $rawPoUom = is_string($poItem->uom) ? $poItem->uom : (optional($poItem->uom)->name ?? $poItem->getRawOriginal('uom') ?? 'PCS');
                        $cleanPoUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $rawPoUom));

                        $poConvFactor = 1;
                        if (preg_match('/\(Isi:\s*([0-9.]+)\)/i', $rawPoUom, $matches)) $poConvFactor = (float) $matches[1];
                        else {
                            $poUomData = \App\Models\ItemUom::where('item_id', $masterItem->id)->where('uom_name', $cleanPoUom)->first();
                            if ($poUomData) $poConvFactor = (float) $poUomData->conversion_qty;
                        }

                        $inputUom = $data['uom'] ?? $rawPoUom; 
                        $cleanInputUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $inputUom));
                        
                        $inputConvFactor = 1;
                        if (preg_match('/\(Isi:\s*([0-9.]+)\)/i', $inputUom, $matches)) $inputConvFactor = (float) $matches[1];
                        else {
                            $inputUomData = \App\Models\ItemUom::where('item_id', $masterItem->id)->where('uom_name', $cleanInputUom)->first();
                            if ($inputUomData) $inputConvFactor = (float) $inputUomData->conversion_qty;
                        }

                        $baseQtyReturned = $inputQty * $inputConvFactor; 
                        $baseQtyReceivedSoFar = ($grItem->qty_received * $poConvFactor); 
                        $baseQtyReturnedSoFar = ($grItem->qty_returned ?? 0) * $poConvFactor;
                        $sisaBaseKuotaGR = round($baseQtyReceivedSoFar - $baseQtyReturnedSoFar, 4);

                        if (round($baseQtyReturned, 4) > $sisaBaseKuotaGR) {
                            throw new \Exception("Jumlah retur ({$baseQtyReturned} {$baseUomName}) melebihi sisa penerimaan dokumen GR!");
                        }

                        $reasonName = 'Alasan Lainnya';
                        if (!empty($data['return_reason_id'])) {
                            $reasonModel = \App\Models\ReturnReason::find($data['return_reason_id']);
                            if ($reasonModel) $reasonName = $reasonModel->name;
                        }

                        // 🔥 PROSES RETUR SERIAL NUMBER 🔥
                        $snRetur = $data['sn'] ?? []; 
                        $snStringRetur = '';
                        $isSnRequired = ($masterItem->is_asset || $masterItem->is_trackable);
                        $hasCheckedSn = !empty($snRetur);

                        if ($isSnRequired && $hasCheckedSn) {
                            $jumlahSnCentang = count($snRetur);
                            $jumlahHarusnya = (int)$baseQtyReturned;

                            if ($jumlahSnCentang !== $jumlahHarusnya) {
                                throw new \Exception("Jumlah centangan Serial Number ({$jumlahSnCentang}) tidak sesuai dengan Qty Retur ({$jumlahHarusnya}) untuk barang {$masterItem->name}!");
                            }

                            // Update tabel item_serials jadi RETURNED dan catat SURAT KEMATIAN
                            \DB::table('item_serials')
                                ->whereIn('serial_number', $snRetur)
                                ->update([
                                    'status'              => 'RETURNED', 
                                    'return_to_vendor_id' => $rtv->id,
                                    'updated_at'          => now()
                                ]);

                            $snStringRetur = implode(' | ', $snRetur);
                        }
                        
                        $catatanKonversi = "Diretur: {$inputQty} {$inputUom} (= {$baseQtyReturned} {$baseUomName}). Alasan: {$reasonName}";
                        if ($snStringRetur) $catatanKonversi .= " [SN Diretur: {$snStringRetur}]";

                        // PEMOTONGAN STOK GLOBAL DARI BATCH
                        if ($masterItem->is_stockable) {
                            $availableStocks = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                                ->where('stock_qty', '>', 0)
                                                ->orderBy('created_at', 'asc')->lockForUpdate()->get();

                            $totalAvailable = $availableStocks->sum('stock_qty');

                            if (round($baseQtyReturned, 4) > round($totalAvailable, 4)) {
                                throw new \Exception("Gagal! Stok fisik '{$masterItem->name}' di sistem tersisa {$totalAvailable} {$baseUomName}, tidak cukup untuk retur.");
                            }

                            $qtySisaRetur = $baseQtyReturned; 
                            foreach ($availableStocks as $stockRow) {
                                if ($qtySisaRetur <= 0) break;
                                $potong = min($stockRow->stock_qty, $qtySisaRetur);
                                $balanceBefore = (float) $stockRow->stock_qty;
                                $stockRow->decrement('stock_qty', $potong);
                                $balanceAfter = $balanceBefore - $potong;
                                $qtySisaRetur -= $potong;

                                StockMutation::create([
                                    'item_id'          => $masterItem->id,
                                    'warehouse_id'     => $stockRow->warehouse_id,
                                    'type'             => 'OUT',
                                    'qty'              => $potong,
                                    'balance_before'   => $balanceBefore,
                                    'balance_after'    => $balanceAfter,
                                    'reference_number' => $rtv->rtv_number,
                                    'notes'            => "Retur ke Vendor. {$catatanKonversi}",
                                    'created_by'       => auth()->id(),
                                ]);
                            }
                            $masterItem->decrement('current_stock', $baseQtyReturned);
                        }

                        // JIKA ASET, UBAH STATUS FIXED ASSET MEMAKAI STATUS_ID
                        if ($masterItem->is_asset && !empty($snRetur)) {
                            $statusRetur = \App\Models\Status::where('type', 'AST')
                                             ->where(function($q) {
                                                 $q->where('slug', 'returned')
                                                   ->orWhere('name', 'like', '%Return%');
                                             })->first();
                                             
                            $statusIdTarget = $statusRetur ? $statusRetur->id : 1;

                            \App\Models\FixedAsset::whereIn('serial_number', $snRetur)->update([
                                'status_id' => $statusIdTarget,
                                'notes'     => 'Dikembalikan ke vendor via RTV: ' . $rtv->rtv_number
                            ]);
                        }

                        // Simpan Item RTV & Update GR/PO
                        ReturnToVendorItem::create([
                            'return_to_vendor_id'    => $rtv->id,
                            'goods_receipt_item_id'  => $grItem->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id'                => $masterItem->id,
                            'qty_returned'           => $inputQty, 
                            'return_reason'          => $catatanKonversi,
                        ]);

                        $poItem->decrement('qty_received', ($baseQtyReturned / $poConvFactor));
                        $grItem->increment('qty_returned', ($baseQtyReturned / $poConvFactor));
                    }
                }

                if ($totalQtyReturnedInThisTransaction == 0) throw new \Exception("Isi minimal 1 qty barang yang diretur.");

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
                            'note' => "Terdapat barang diretur (RTV: {$rtv->rtv_number}). Status PO dikembalikan ke Parsial."
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




    public function print($slug) {
        $rtv = ReturnToVendor::with(['vendor', 'goodsReceipt.po.company', 'returner', 'items.item', 'items.purchaseOrderItem'])->where('rtv_number', $slug)->firstOrFail();
        return view('rtv.print', compact('rtv'));
    }
}