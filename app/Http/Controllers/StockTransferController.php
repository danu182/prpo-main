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

                    // Deteksi Mode
                    // 🔥 PERBAIKAN: Jika item tipenya AST ATAU form mengirimkan data Nomor Aset, paksa jadi Mode Aset!
                    $isModeAsset = !empty($data['asset_ids']);

                    // ========================================================
                    // JALUR 1: ASET TETAP (MAJOR ASSET)
                    // ========================================================
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

                        // Pindahkan lokasi gudang
                        \App\Models\FixedAsset::whereIn('id', $assetIds)->update(['warehouse_id' => $request->to_warehouse_id]);

                        $itemNoteCombined = implode(' | ', $snArr) . ($itemNote ? " | " . $itemNote : "");

                        StockTransferItem::create([
                            'stock_transfer_id'  => $transfer->id,
                            'item_id'            => $item->id,
                            'inventory_stock_id' => null, // Aset tidak terikat pada kartu stok
                            'qty_transferred'    => count($assetIds),
                            'uom_id'             => null,
                            'uom'                => 'Unit',
                            'notes'              => $itemNoteCombined,
                        ]);

                        continue; // 🔥 ABAIKAN JALUR 2, LANJUT BARANG BERIKUTNYA 🔥
                    }

                    // ========================================================
                    // JALUR 2: BARANG STOK BIASA & STOK LACAK (MINOR)
                    // ========================================================
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

                    // KELUAR DARI GUDANG ASAL
                    $query = InventoryStock::where('warehouse_id', $request->from_warehouse_id)
                                ->where('item_id', $item->id)->where('stock_qty', '>', 0);

                    if (!empty($data['inventory_stock_id'])) { $query->where('id', $data['inventory_stock_id']); }
                    else { $query->orderBy('created_at', 'asc'); }

                    $availableStocks = $query->lockForUpdate()->get();
                    $totalAvailable = $availableStocks->sum('stock_qty');

                    if (round($totalAvailable, 4) < round($baseQtyRequested, 4)) {
                        throw new \Exception("Stok {$item->name} di Gudang Asal tidak cukup!");
                    }

                    $qtySisa = $baseQtyRequested;
                    $sourceBatchIds = [];

                    foreach ($availableStocks as $stockRow) {
                        if ($qtySisa <= 0) break;
                        $potong = min($stockRow->stock_qty, $qtySisa);
                        $sourceBatchIds[] = $stockRow->batch_id;

                        $balanceBefore = $item->current_stock;
                        $stockRow->decrement('stock_qty', $potong);
                        $qtySisa -= $potong;

                        StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->from_warehouse_id,
                            'type'             => 'OUT',
                            'qty'              => $potong,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceBefore,
                            'reference_number' => $tfNumber,
                            'notes'            => "Transfer KELUAR ke " . Warehouse::find($request->to_warehouse_id)->name,
                            'created_by'       => auth()->id(),
                        ]);
                    }

                    // MASUK KE GUDANG TUJUAN
                    $companyId = auth()->user()->company_id ?? 1;
                    $newStock = InventoryStock::where('item_id', $item->id)
                                        ->where('warehouse_id', $request->to_warehouse_id)
                                        ->first();

                    if ($newStock) { $newStock->increment('stock_qty', $baseQtyRequested); }
                    else {
                        $newStock = InventoryStock::create([
                            'company_id'       => $companyId,
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->to_warehouse_id,
                            'stock_qty'        => $baseQtyRequested,
                            'batch_id'         => !empty($sourceBatchIds) ? implode(',', array_filter($sourceBatchIds)) : null,
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

                $formattedItems[] = [
                    'id'              => $item->id,
                    'text'            => '[' . $item->code . '] ' . $item->name,
                    'available_bulk'  => (float)$bulkStock,
                    'available_asset' => (int)$assetStock,
                    'base_uom'        => $item->uom->name ?? 'PCS',
                    'uoms'            => $item->uoms,
                    'is_asset'        => $item->item_type_code === 'AST', // 🔥 Kunci Ajaib!
                    'is_trackable'    => $item->is_trackable ?? 0
                ];
            }
            return response()->json($formattedItems);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
