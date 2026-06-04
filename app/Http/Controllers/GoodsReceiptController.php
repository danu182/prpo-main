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
        
        // 🔥 UBAH FORMAT DARI GARIS MIRING (/) MENJADI STRIP (-) 🔥
        $prefix = "GR-{$companyCode}-{$year}-{$month}-";

        $lastGr = \App\Models\GoodsReceipt::where('gr_number', 'LIKE', "{$prefix}%")
                    ->orderBy('id', 'desc')
                    ->first();

        $nextSequence = $lastGr ? ((int) substr($lastGr->gr_number, -4)) + 1 : 1;

        return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }



    // ========================================================
    // PRIVATE FUNCTION: PENCETAK SERIAL NUMBER BATCH OTOMATIS
    // ========================================================
    private function generateSnBatch($itemCode, $countNeeded)
    {
        if ($countNeeded <= 0) return [];

        $snPrefix = $itemCode . '-' . date('Ym') . '-';
        
        // Kunci tabel agar nomor urut tidak tabrakan jika ada 2 user menyimpan di saat bersamaan
        $lastRecord = \DB::table('item_serials')
                        ->where('serial_number', 'like', "{$snPrefix}%")
                        ->orderBy('serial_number', 'desc')
                        ->lockForUpdate() 
                        ->first();

        $nextSeq = $lastRecord ? ((int) substr($lastRecord->serial_number, -4)) + 1 : 1;

        $generatedSns = [];
        for ($i = 0; $i < $countNeeded; $i++) {
            $generatedSns[] = $snPrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            $nextSeq++; // Langsung dinaikkan agar array isinya urut
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
    // CREATE GR (HALAMAN PENERIMAAN BARANG)
    // ========================================================
    public function create($slug)
    {
        $po = \App\Models\PurchaseOrder::with([
            'vendor', 
            'items.item.itemUoms', 
            'items.item.uom'
        ])->where('po_number', $slug)->firstOrFail();

        $pendingItems = $po->items->filter(function ($item) {
            // 🔥 TARIK SATUAN UOM AKTUAL DARI PO (JANGAN MENEBAK DARI MASTER) 🔥
            $rawPoUom = is_string($item->uom) ? $item->uom : (optional($item->uom)->name ?? $item->getRawOriginal('uom') ?? 'PCS');
            
            // Ekstrak angka konversi (Toleran terhadap kata tambahan seperti 'Pieces')
            $poConvFactor = 1;
            if (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                $poConvFactor = (float) $matches[1];
            }

            // Hitung sisa berdasarkan Satuan PO (Bukan Eceran)
            $receivedPoUom = ($item->qty_received ?? 0); // Karena qty_received di tabel PO sudah disimpan dalam format UOM PO
            $sisaPoUom = (float) $item->qty_ordered - $receivedPoUom;

            // Simpan variabel sisa ke dalam objek agar bisa dibaca di Blade
            $item->sisa_po_uom = $sisaPoUom;
            $item->po_conv_factor = $poConvFactor;
            $item->raw_po_uom = $rawPoUom;

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
    // STORE GR (PENERIMAAN BARANG DENGAN AKTE KELAHIRAN SN)
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

                // 1. Simpan Header GR
                $gr = \App\Models\GoodsReceipt::create([
                    'purchase_order_id'    => $po->id,
                    'gr_number'            => $grNumber,
                    'delivery_note_number' => $request->delivery_note_number,
                    'received_date'        => $request->receipt_date,
                    'received_by'          => auth()->id(),
                    'notes'                => $request->notes,
                ]);

                // 2. Simpan Lampiran (Bisa Banyak File)
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

                // 3. Proses per Item yang diterima
                foreach ($request->items as $itemId => $data) {
                    $inputQty = (float) $data['qty_received'];

                    if ($inputQty > 0) {
                        $poItem = \App\Models\PurchaseOrderItem::findOrFail($itemId);
                        $masterItem = \App\Models\Item::with('uom', 'itemUoms')->findOrFail($data['item_id']);
                        $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                        $rawPoUom = is_string($poItem->uom) ? $poItem->uom : (optional($poItem->uom)->name ?? $poItem->getRawOriginal('uom') ?? 'PCS');
                        $cleanPoUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $rawPoUom));

                        $poConvFactor = 1;
                        if (preg_match('/\(Isi:\s*([0-9.]+)/i', $rawPoUom, $matches)) $poConvFactor = (float) $matches[1];
                        else {
                            $poUomData = collect($masterItem->itemUoms)->where('uom_name', $cleanPoUom)->first();
                            if ($poUomData) $poConvFactor = (float) $poUomData->conversion_qty;
                        }

                        $inputUom = $data['uom'] ?? $rawPoUom;
                        $cleanInputUom = trim(preg_replace('/ \(Isi:.*\)/i', '', $inputUom));

                        $inputConvFactor = 1;
                        if (preg_match('/\(Isi:\s*([0-9.]+)/i', $inputUom, $matches)) $inputConvFactor = (float) $matches[1];
                        else {
                            $inputUomData = collect($masterItem->itemUoms)->where('uom_name', $cleanInputUom)->first();
                            if ($inputUomData) $inputConvFactor = (float) $inputUomData->conversion_qty;
                        }

                        $baseQtyReceived = $inputQty * $inputConvFactor; 
                        $baseQtyOrdered = $poItem->qty_ordered * $poConvFactor; 
                        $baseQtyReceivedSoFar = ($poItem->qty_received ?? 0) * $poConvFactor; 
                        $sisaBaseYangBolehDiterima = round($baseQtyOrdered - $baseQtyReceivedSoFar, 4);

                        if (round($baseQtyReceived, 4) > $sisaBaseYangBolehDiterima) throw new \Exception("Kuantitas terima ({$baseQtyReceived} {$baseUomName}) melebihi sisa pesanan PO!");

                        // ==============================================================
                        // 🔥 SIHIR AUTO-GENERATE SERIAL NUMBER 🔥
                        // ==============================================================
                        $snArray = $data['sn'] ?? [];
                        $finalSnList = [];
                        $requiresSn = ($masterItem->is_asset || $masterItem->is_trackable);
                        $qtyInt = (int) $baseQtyReceived;

                        if ($requiresSn && $qtyInt > 0) {
                            $itemCodeForSn = $masterItem->code ?? 'ITM';
                            
                            $autoCountNeeded = 0;
                            for ($i = 0; $i < $qtyInt; $i++) {
                                $inputSn = isset($snArray[$i]) ? trim($snArray[$i]) : '';
                                if ($inputSn === '' || strtoupper($inputSn) === '[AUTO]') {
                                    $autoCountNeeded++;
                                }
                            }

                            $autoGeneratedSns = $this->generateSnBatch($itemCodeForSn, $autoCountNeeded);
                            $autoIndex = 0;

                            for ($i = 0; $i < $qtyInt; $i++) {
                                $inputSn = isset($snArray[$i]) ? trim($snArray[$i]) : '';
                                if ($inputSn === '' || strtoupper($inputSn) === '[AUTO]') {
                                    $finalSnList[] = $autoGeneratedSns[$autoIndex];
                                    $autoIndex++;
                                } else {
                                    $finalSnList[] = $inputSn; 
                                }
                            }

                            // Simpan langsung ke tabel pelacakan khusus (item_serials)
                            foreach ($finalSnList as $snFinal) {
                                \DB::table('item_serials')->insert([
                                    'item_id'          => $masterItem->id,
                                    'warehouse_id'     => $request->warehouse_id ?? 1,
                                    'goods_receipt_id' => $gr->id, // 🔥 AKTE KELAHIRAN
                                    'serial_number'    => $snFinal,
                                    'status'           => 'AVAILABLE',
                                    'current_user_id'  => null,
                                    'created_at'       => now(),
                                    'updated_at'       => now(),
                                ]);
                            }
                        }

                        // ==============================================================
                        // 🔥 SMART TRUNCATION UNTUK KARTU MUTASI STOK 🔥
                        // ==============================================================
                        $snString = '';
                        if (!empty($finalSnList)) {
                            if (count($finalSnList) > 3) {
                                $firstFew = array_slice($finalSnList, 0, 3);
                                $sisaCount = count($finalSnList) - 3;
                                $snString = implode(' | ', $firstFew) . " | ... (+{$sisaCount} unit lainnya)";
                            } else {
                                $snString = implode(' | ', $finalSnList);
                            }
                        }

                        $catatanAsli = $data['notes'] ?? null;

                        // Simpan Item GR (Hanya simpan ketikan staf gudang, bersih dari String SN)
                        \App\Models\GoodsReceiptItem::create([
                            'goods_receipt_id'       => $gr->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id'                => $data['item_id'],
                            'qty_received'           => $inputQty, 
                            'uom'                    => $cleanInputUom, // 🔥 INI DIA KOLOM BARUNYA!
                            'condition_id'           => $data['condition_id'],
                            'notes'                  => $catatanAsli, 
                        ]);

                        $poItem->qty_received = ($poItem->qty_received ?? 0) + ($baseQtyReceived / $poConvFactor);
                        $poItem->save();

                        // E. SIHIR STOK (Untuk Barang Stok Biasa / Trackable Minor)
                        if ($masterItem->is_stockable) {
                            $balanceBefore = (float) $masterItem->current_stock;
                            $balanceAfter  = $balanceBefore + $baseQtyReceived; 
                            $namaSpesifik = $poItem->description ?? $masterItem->name;

                            // Gabungan Ketikan Staf dan Truncated SN untuk Buku Mutasi Gudang
                            $noteMutasi = $catatanAsli ? "Masuk: {$namaSpesifik} (Ref PO: {$po->po_number}) - {$catatanAsli}" : "Masuk: {$namaSpesifik} (Ref PO: {$po->po_number})";
                            if ($snString) {
                                $noteMutasi .= " [SN: {$snString}]";
                            }

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

                        // F. SIHIR ASET (Aset Tetap Mayor)
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

                // 4. Update Status PO & PR
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

    // ========================================================
    // SHOW GR (DETAIL PENERIMAAN BARANG DENGAN PELACAKAN SN)
    // ========================================================
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

        // 🔥 Menarik Akte Kelahiran secara presisi dari Database
        foreach ($gr->items as $grItem) {
            $grItem->registered_sns = \DB::table('item_serials')
                ->where('item_id', $grItem->item_id)
                ->where('goods_receipt_id', $gr->id)
                ->pluck('serial_number')
                ->toArray();
        }

        return view('gr.show', compact('gr'));
    }



    // ========================================================
    // PRINT GR
    // ========================================================
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

        // 🔥 TARIK DATA SN DARI TABEL PELACAKAN (Sama seperti halaman Show) 🔥
        foreach ($gr->items as $grItem) {
            $grItem->registered_sns = \DB::table('item_serials')
                ->where('item_id', $grItem->item_id)
                ->where('goods_receipt_id', $gr->id)
                ->pluck('serial_number')
                ->toArray();
        }

        return view('gr.print', compact('gr'));
    }

    // ========================================================
    // PRINT LABELS GR
    // ========================================================
    public function printLabels($slug)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'po.company',
            'items.item',
            'items.purchaseOrderItem'
        ])->where('gr_number', $slug)->firstOrFail(); // 🔥 UBAH KE PENCARIAN SLUG

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