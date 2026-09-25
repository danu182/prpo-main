<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator', 'items.item'])
            ->when($search, function($q) use ($search) {
                $q->where('transfer_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15);

        return view('stock_transfers.index', compact('transfers', 'search'));
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('name', 'asc')->get();
        return view('stock_transfers.create', compact('warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'transfer_date'     => 'required|date|before_or_equal:today',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'items'             => 'required|array|min:1',
        ], ['to_warehouse_id.different' => 'Gudang Tujuan TIDAK BOLEH SAMA dengan Gudang Asal!']);

        try {
            DB::transaction(function () use ($request) {
                $year = date('Y', strtotime($request->transfer_date));
                $month = date('m', strtotime($request->transfer_date));
                $lastTf = StockTransfer::whereYear('created_at', $year)->whereMonth('created_at', $month)->orderBy('id', 'desc')->first();
                $nextId = $lastTf ? ((int) substr($lastTf->transfer_number, -4)) + 1 : 1;
                $tfNumber = 'TF/' . $year . '/' . $month . '/' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                $transfer = StockTransfer::create([
                    'transfer_number'   => $tfNumber,
                    'transfer_date'     => $request->transfer_date,
                    'from_warehouse_id' => $request->from_warehouse_id,
                    'to_warehouse_id'   => $request->to_warehouse_id,
                    'notes'             => $request->notes,
                    'created_by'        => auth()->id(),
                ]);

                foreach ($request->items as $data) {
                    $item = Item::with('uom', 'itemUoms')->findOrFail($data['item_id']);
                    $baseUomName = optional($item->uom)->name ?? 'PCS';
                    $itemNote = $data['notes'] ?? null;

                    $specificName = $data['item_name'] ?? $item->name;
                    $isModeAsset = !empty($data['asset_ids']);

                    if ($isModeAsset) {
                        if (empty($data['asset_ids'])) throw new \Exception("Aset untuk barang {$item->name} belum dipilih!");

                        $assetIds = $data['asset_ids'];
                        $assetDetails = \App\Models\FixedAsset::whereIn('id', $assetIds)->get();
                        $snArr = [];

                        foreach($assetDetails as $ad) {
                            $snArr[] = $ad->asset_number . ($ad->serial_number ? " (SN: {$ad->serial_number})" : "");

                            \App\Models\FixedAssetHistory::create([
                                'fixed_asset_id' => $ad->id,
                                'status'         => 'Transfer Antar Gudang',
                                'notes'          => "Dipindahkan ke " . Warehouse::find($request->to_warehouse_id)->name . " via Dok: {$tfNumber}",
                                'created_by'     => auth()->id(),
                            ]);
                        }

                        \App\Models\FixedAsset::whereIn('id', $assetIds)->update(['warehouse_id' => $request->to_warehouse_id]);

                        $itemNoteCombined = implode(' | ', $snArr) . ($itemNote ? " | " . $itemNote : "");

                        StockTransferItem::create([
                            'stock_transfer_id'  => $transfer->id,
                            'item_id'            => $item->id,
                            'item_name'          => $specificName,
                            'inventory_stock_id' => null,
                            'qty_transferred'    => count($assetIds),
                            'uom_id'             => null,
                            'uom'                => 'Unit',
                            'notes'              => $itemNoteCombined,
                        ]);

                        continue;
                    }

                    $qtyInput = (float) $data['qty'];
                    $uomId = $data['uom_info'] ?? null;
                    $conversionFactor = 1;
                    $cleanUomName = $baseUomName;

                    if (!empty($uomId)) {
                        $uomDb = \App\Models\ItemUom::find($uomId);
                        if ($uomDb) {
                            $conversionFactor = (float) $uomDb->conversion_qty;
                            $cleanUomName = $uomDb->uom_name;
                        }
                    }

                    $finalUomString = $cleanUomName . ($conversionFactor > 1 ? " (Isi {$conversionFactor} {$baseUomName})" : "");
                    $baseQtyRequested = $qtyInput * $conversionFactor;

                    if ($baseQtyRequested <= 0) throw new \Exception("Kuantitas {$item->name} tidak boleh 0!");

                    // 🔥 VALIDASI KETAT JIKA USER MEMILIH BATCH TERTENTU (BUKAN AUTO-FIFO) 🔥
                    $selectedBatchRef = $data['inventory_stock_id'] ?? null;
                    
                    if (!empty($selectedBatchRef) && !is_numeric($selectedBatchRef)) {
                        $tempTotalOut = \App\Models\StockMutation::where('item_id', $item->id)
                            ->where('warehouse_id', $request->from_warehouse_id)
                            ->where('type', '!=', 'IN')
                            ->sum('qty');

                        $tempInMutations = \App\Models\StockMutation::where('item_id', $item->id)
                            ->where('warehouse_id', $request->from_warehouse_id)
                            ->where('type', 'IN')
                            ->orderBy('created_at', 'asc')
                            ->orderBy('id', 'asc')
                            ->get();

                        $sisaBatchDipilih = 0;
                        foreach ($tempInMutations as $mut) {
                            if ($tempTotalOut >= $mut->qty) {
                                $tempTotalOut -= $mut->qty;
                                continue;
                            }
                            $sisa = $mut->qty - $tempTotalOut;
                            $tempTotalOut = 0;

                            if ($mut->reference_number === $selectedBatchRef) {
                                $sisaBatchDipilih = $sisa;
                                break;
                            }
                        }

                        if (round($baseQtyRequested, 4) > round($sisaBatchDipilih, 4)) {
                            throw new \Exception("DITOLAK! Sisa fisik stok dari dokumen [{$selectedBatchRef}] di gudang ini hanya tersisa {$sisaBatchDipilih} {$baseUomName}, tetapi Anda mencoba memindahkan {$baseQtyRequested}!");
                        }
                    }

                    // PROSES PENGURANGAN STOK GUDANG ASAL
                    $query = InventoryStock::where('warehouse_id', $request->from_warehouse_id)
                                ->where('item_id', $item->id)->where('stock_qty', '>', 0);

                    if (!empty($selectedBatchRef) && is_numeric($selectedBatchRef)) { 
                        $query->where('id', $selectedBatchRef); 
                    } else { 
                        $query->orderBy('created_at', 'asc'); 
                    }

                    $availableStocks = $query->lockForUpdate()->get();
                    $totalAvailable = $availableStocks->sum('stock_qty');

                    if (round($totalAvailable, 4) < round($baseQtyRequested, 4)) {
                        throw new \Exception("Stok {$item->name} di Gudang Asal secara total tidak cukup!");
                    }

                    $qtySisa = $baseQtyRequested;
                    $sourceBatchIds = [];
                    
                    if (!empty($selectedBatchRef) && !is_numeric($selectedBatchRef)) {
                        $sourceBatchIds[] = $selectedBatchRef; // Tangkap nama referensi dokumen
                    }

                    foreach ($availableStocks as $stockRow) {
                        if ($qtySisa <= 0) break;
                        $potong = min($stockRow->stock_qty, $qtySisa);
                        
                        if (empty($selectedBatchRef)) {
                            $sourceBatchIds[] = $stockRow->batch_id ?? 'REGULER';
                        }

                        $balanceBefore = $item->current_stock;
                        $stockRow->decrement('stock_qty', $potong);
                        $qtySisa -= $potong;

                        $catatanRef = (!empty($selectedBatchRef) && !is_numeric($selectedBatchRef)) ? " (Target Ref: {$selectedBatchRef})" : "";

                        StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->from_warehouse_id,
                            'type'             => 'OUT',
                            'qty'              => $potong,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceBefore,
                            'reference_number' => $tfNumber,
                            'notes'            => "Transfer KELUAR ke " . Warehouse::find($request->to_warehouse_id)->name . $catatanRef,
                            'created_by'       => auth()->id(),
                        ]);
                    }

                    // MASUK KE GUDANG TUJUAN
                    $companyId = auth()->user()->company_id ?? 1;
                    $newStock = InventoryStock::where('item_id', $item->id)
                                        ->where('warehouse_id', $request->to_warehouse_id)
                                        ->first();

                    $batchLabel = !empty($sourceBatchIds) ? implode(', ', array_unique(array_filter($sourceBatchIds))) : null;

                    if ($newStock) { 
                        $newStock->increment('stock_qty', $baseQtyRequested); 
                    } else {
                        $newStock = InventoryStock::create([
                            'company_id'       => $companyId,
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->to_warehouse_id,
                            'stock_qty'        => $baseQtyRequested,
                            'batch_id'         => $batchLabel,
                            'reference_number' => $tfNumber,
                        ]);
                    }

                    StockMutation::create([
                        'item_id'          => $item->id,
                        'warehouse_id'     => $request->to_warehouse_id,
                        'type'             => 'IN',
                        'qty'              => $baseQtyRequested,
                        'balance_before'   => $item->current_stock,
                        'balance_after'    => $item->current_stock,
                        'reference_number' => $tfNumber,
                        'notes'            => "Transfer MASUK dari " . Warehouse::find($request->from_warehouse_id)->name,
                        'created_by'       => auth()->id(),
                    ]);

                    StockTransferItem::create([
                        'stock_transfer_id'  => $transfer->id,
                        'item_id'            => $item->id,
                        'item_name'          => $specificName,
                        'inventory_stock_id' => $newStock->id,
                        'qty_transferred'    => $qtyInput,
                        'uom_id'             => $uomId ?: null,
                        'uom'                => $finalUomString,
                        'notes'              => $itemNote,
                    ]);
                }
            });

            return redirect()->route('stock-transfers.index')->with('success', 'Transfer Antar Gudang Berhasil Diproses!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $transfer = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator', 'items.item'])->findOrFail($id);
        return view('stock_transfers.show', compact('transfer'));
    }

    public function searchItems(Request $request)
    {
        $search = $request->search;
        $warehouseId = $request->warehouse_id;

        if (!$warehouseId) return response()->json([]);

        try {
            $stockItemIds = \App\Models\InventoryStock::where('warehouse_id', $warehouseId)
                        ->where('stock_qty', '>', 0)
                        ->pluck('item_id')->toArray();

            $assetItemIds = \App\Models\FixedAsset::where('warehouse_id', $warehouseId)
                        ->whereHas('status', function($q) { $q->where('slug', 'available'); })
                        ->pluck('item_id')->toArray();

            $mergedItemIds = array_unique(array_merge($stockItemIds, $assetItemIds));

            $items = \App\Models\Item::with(['uom', 'uoms'])
                        ->whereIn('id', $mergedItemIds)
                        ->where(function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->limit(20)
                        ->get();

            $formattedItems = [];
            foreach ($items as $item) {
                $bulkStock = \App\Models\InventoryStock::where('warehouse_id', $warehouseId)
                                    ->where('item_id', $item->id)
                                    ->sum('stock_qty');

                $assetStock = \App\Models\FixedAsset::where('warehouse_id', $warehouseId)
                                    ->where('item_id', $item->id)
                                    ->whereHas('status', function($q) { $q->where('slug', 'available'); })
                                    ->count();

                $historicalNames = \App\Models\PurchaseOrderItem::where('item_id', $item->id)
                                    ->whereNotNull('item_name')->distinct()->pluck('item_name')->toArray();

                $latestPoItem = \App\Models\PurchaseOrderItem::where('item_id', $item->id)
                                    ->whereNotNull('item_name')->latest('id')->first();

                $defaultSpecificName = $latestPoItem ? $latestPoItem->item_name : $item->name;

                if (!in_array($item->name, $historicalNames)) {
                    array_unshift($historicalNames, $item->name);
                }

                $formattedItems[] = [
                    'id'              => $item->id,
                    'text'            => '[' . $item->code . '] ' . $item->name,
                    'raw_name'        => $item->name,
                    'historical_names'=> $historicalNames,
                    'default_specific_name' => $defaultSpecificName,
                    'available_bulk'  => (float)$bulkStock,
                    'available_asset' => (int)$assetStock,
                    'base_uom'        => $item->uom->name ?? 'PCS',
                    'uoms'            => $item->uoms,
                    'is_asset'        => $item->item_type_code === 'AST',
                    'is_trackable'    => $item->is_trackable ?? 0
                ];
            }
            return response()->json($formattedItems);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function searchFixedAssets(Request $request)
    {
        $search = $request->search;
        $itemId = $request->item_id;
        $warehouseId = $request->warehouse_id;

        try {
            $assets = \App\Models\FixedAsset::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->whereHas('status', function($q) {
                    $q->where('slug', 'available');
                })
                ->when($search, function($query) use ($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('asset_number', 'like', "%{$search}%")
                          ->orWhere('serial_number', 'like', "%{$search}%")
                          ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                ->limit(50)
                ->get();

            $formatted = [];
            foreach ($assets as $asset) {
                $text = $asset->asset_number . ' (' . $asset->name . ')';
                if (!empty($asset->serial_number)) $text .= ' | SN: ' . $asset->serial_number;
                $formatted[] = ['id' => $asset->id, 'text' => $text];
            }

            return response()->json($formatted);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error Search Asset Transfer: " . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan sistem'], 500);
        }
    }

    // 🔥 METODE CERDAS: MENCARI BATCH BERDASARKAN REFERENSI MASUK 🔥
    public function searchBatches(Request $request)
    {
        try {
            $itemId = $request->item_id;
            $warehouseId = $request->warehouse_id;
            $search = $request->search;

            // 1. Dapatkan Total Barang Keluar untuk di-FIFO-kan
            $totalOut = \App\Models\StockMutation::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->where('type', '!=', 'IN')
                ->sum('qty');

            // 2. Dapatkan Riwayat Barang Masuk
            $inMutations = \App\Models\StockMutation::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->where('type', 'IN')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $formatted = [];
            foreach ($inMutations as $mut) {
                // Lewati batch jika sudah habis terpakai
                if ($totalOut >= $mut->qty) {
                    $totalOut -= $mut->qty;
                    continue; 
                }

                // Sisa bersih dari dokumen masuk ini
                $sisaBatch = $mut->qty - $totalOut;
                $totalOut = 0;

                // Lacak nama vendor jika dokumennya adalah Penerimaan (GR)
                $infoAsal = '';
                if (str_starts_with($mut->reference_number, 'GR')) {
                    try {
                        $gr = \App\Models\GoodsReceipt::with('po.vendor')->where('gr_number', $mut->reference_number)->first();
                        if ($gr && $gr->po && $gr->po->vendor) {
                            $infoAsal = " (Vendor: " . \Illuminate\Support\Str::limit($gr->po->vendor->name, 15) . ")";
                        }
                    } catch (\Exception $e) {}
                }

                $text = "Ref: {$mut->reference_number}{$infoAsal} | Tgl: " . $mut->created_at->format('d/m/y') . " ➔ Sisa: " . (float)$sisaBatch;

                // Filter Pencarian di Kotak Dropdown
                if ($search && stripos($text, $search) === false) {
                    continue;
                }

                $formatted[] = [
                    'id'   => $mut->reference_number, // Kunci: Mengirimkan Nama Dokumennya!
                    'text' => $text,
                    'sisa' => (float)$sisaBatch
                ];
            }

            return response()->json($formatted);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error Search Batches Transfer: " . $e->getMessage());
            return response()->json(['error' => 'Gagal memuat batch'], 500);
        }
    }

    public function printTransfer($id)
    {
        $transfer = StockTransfer::with([
            'fromWarehouse', 'toWarehouse', 'creator', 'items.item'
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('stock_transfers.print', compact('transfer'))
                ->setPaper('A4', 'portrait');

        $namaFile = str_replace('/', '_', $transfer->transfer_number);
        return $pdf->stream('Surat_Jalan_Mutasi_' . $namaFile . '.pdf');
    }
}