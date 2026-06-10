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
        ], [
            'to_warehouse_id.different' => 'Gudang Tujuan TIDAK BOLEH SAMA dengan Gudang Asal!'
        ]);

        try {
            DB::transaction(function () use ($request) {
                // 1. Generate Nomor Transfer
                $year = date('Y', strtotime($request->transfer_date));
                $month = date('m', strtotime($request->transfer_date));
                $lastTf = StockTransfer::whereYear('created_at', $year)->whereMonth('created_at', $month)->orderBy('id', 'desc')->first();
                $nextId = $lastTf ? ((int) substr($lastTf->transfer_number, -4)) + 1 : 1;
                $tfNumber = 'TF/' . $year . '/' . $month . '/' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                // 2. Buat Surat Jalan Transfer
                $transfer = StockTransfer::create([
                    'transfer_number'   => $tfNumber,
                    'transfer_date'     => $request->transfer_date,
                    'from_warehouse_id' => $request->from_warehouse_id,
                    'to_warehouse_id'   => $request->to_warehouse_id,
                    'notes'             => $request->notes,
                    'created_by'        => auth()->id(),
                ]);

                // 3. Proses Perpindahan Barang per Baris
                foreach ($request->items as $data) {
                    $item = Item::with('uom', 'itemUoms')->findOrFail($data['item_id']);
                    $baseUomName = optional($item->uom)->name ?? 'PCS';

                    $isModeAsset = $item->is_asset && !empty($data['asset_ids']);

                    $qtyInput = 0;
                    $conversionFactor = 1;
                    $finalUomString = $baseUomName;
                    $uomId = null;

                    if ($isModeAsset) {
                        $qtyInput = count($data['asset_ids']);
                        $baseQtyRequested = $qtyInput;
                    } else {
                        $qtyInput = (float) $data['qty'];
                        $uomId = $data['uom_info'] ?? null;

                        $cleanUomName = $baseUomName;

                        if (!empty($uomId)) {
                            $uomDb = \App\Models\ItemUom::find($uomId);
                            if ($uomDb) {
                                $conversionFactor = (float) $uomDb->conversion_qty;
                                $cleanUomName = $uomDb->uom_name;
                            }
                        }

                        $finalUomString = $cleanUomName;
                        if ($conversionFactor > 1) {
                            $finalUomString .= ' (Isi ' . $conversionFactor . ' ' . $baseUomName . ')';
                        }

                        // 🔥 Hitung Base Qty (Kuantitas Terkecil)
                        $baseQtyRequested = $qtyInput * $conversionFactor;
                    }

                    if ($baseQtyRequested <= 0) {
                        throw new \Exception("Gagal: Kuantitas barang {$item->name} tidak boleh kosong (0)!");
                    }

                    $itemNote = $data['notes'] ?? null;

                    // TAHAP A: AMBIL DARI GUDANG ASAL (OUT)
                    $query = InventoryStock::where('warehouse_id', $request->from_warehouse_id)
                                ->where('item_id', $item->id)
                                ->where('stock_qty', '>', 0);

                    if (!empty($data['inventory_stock_id'])) {
                        $query->where('id', $data['inventory_stock_id']);
                    } else {
                        $query->orderBy('created_at', 'asc'); // FIFO
                    }

                    $availableStocks = $query->lockForUpdate()->get();
                    $totalAvailable = $availableStocks->sum('stock_qty');

                    if (round($totalAvailable, 4) < round($baseQtyRequested, 4)) {
                        throw new \Exception("Stok {$item->name} di Gudang Asal tidak cukup! Diminta: {$baseQtyRequested} {$baseUomName}, Tersedia: {$totalAvailable} {$baseUomName}");
                    }

                    $qtySisa = $baseQtyRequested;
                    $sourceBatchIds = [];

                    foreach ($availableStocks as $stockRow) {
                        if ($qtySisa <= 0) break;
                        $potong = min($stockRow->stock_qty, $qtySisa);

                        $sourceBatchIds[] = $stockRow->batch_id;

                        $balanceBefore = $item->current_stock; // Asumsi stock global
                        $stockRow->decrement('stock_qty', $potong);
                        $qtySisa -= $potong;

                        StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->from_warehouse_id,
                            'type'             => 'OUT',
                            'qty'              => $potong,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceBefore, // Mutasi antar gudang tidak mengubah total stok perusahaan
                            'reference_number' => $tfNumber,
                            'notes'            => "Transfer KELUAR ke " . Warehouse::find($request->to_warehouse_id)->name,
                            'created_by'       => auth()->id(),
                        ]);
                    }

                    // TAHAP B: MASUKKAN KE GUDANG TUJUAN (IN)
                    $companyId = auth()->user()->company_id ?? 1;

                    $newStock = InventoryStock::where('item_id', $item->id)
                                              ->where('warehouse_id', $request->to_warehouse_id)
                                              ->first();

                    if ($newStock) {
                        $newStock->increment('stock_qty', $baseQtyRequested);
                    } else {
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

                    // TAHAP C: JIKA ASET TETAP
                    if ($isModeAsset) {
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

                        \App\Models\FixedAsset::whereIn('id', $assetIds)->update([
                            'warehouse_id' => $request->to_warehouse_id
                        ]);

                        $itemNote = implode(' | ', $snArr) . ($itemNote ? " | " . $itemNote : "");
                    }

                    // 4. Simpan Detail Transfer
                    StockTransferItem::create([
                        'stock_transfer_id'  => $transfer->id,
                        'item_id'            => $item->id,
                        'inventory_stock_id' => $newStock->id,
                        'qty_transferred'    => $qtyInput, // Simpan angka yang diketik user
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

    // =========================================================================
    // FUNGSI PENCARIAN BARANG KHUSUS MUTASI GUDANG
    // =========================================================================
    public function searchItems(Request $request)
    {
        $search = $request->search;
        $warehouseId = $request->warehouse_id;

        if (!$warehouseId) {
            return response()->json([]);
        }

        try {
            $itemIds = \App\Models\InventoryStock::where('warehouse_id', $warehouseId)
                        ->where('stock_qty', '>', 0)
                        ->pluck('item_id')->unique();

            $items = \App\Models\Item::with(['uom', 'uoms']) // 🔥 TARIK RELASI UOM
                        ->whereIn('id', $itemIds)
                        ->where(function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->limit(20)
                        ->get();

            $formattedItems = [];
            foreach ($items as $item) {
                $currentStock = \App\Models\InventoryStock::where('warehouse_id', $warehouseId)
                                    ->where('item_id', $item->id)
                                    ->sum('stock_qty');

                $formattedItems[] = [
                    'id'           => $item->id,
                    'text'         => '[' . $item->code . '] ' . $item->name . ' (Tersedia: ' . (float)$currentStock . ' ' . ($item->uom->name ?? 'PCS') . ')',
                    'stock'        => (float)$currentStock,
                    'base_uom'     => $item->uom->name ?? 'PCS',
                    'uoms'         => $item->uoms, // 🔥 Kirim data multiple kemasan
                    'is_asset'     => $item->is_asset,
                    'is_trackable' => $item->is_trackable ?? 0
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
