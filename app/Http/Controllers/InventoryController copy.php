<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // <--- INI YANG BENAR
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    // 1. Pemakaian Stok (Usage)
    public function storeUsage(Request $request)
    {
        $request->validate([
            'item_id' => 'required',
            'qty' => 'required|integer|min:1',
            'notes' => 'required'
        ]);

        try {
            DB::transaction(function () use ($request) {
                $userCompanyId = auth()->user()->company_id;

                // Cek Stok
                $stock = InventoryStock::where('company_id', $userCompanyId)
                            ->where('item_id', $request->item_id)
                            ->lockForUpdate() // Cegah race condition
                            ->firstOrFail();

                if ($stock->stock_qty < $request->qty) {
                    throw new \Exception("Stok tidak mencukupi. Sisa: " . $stock->stock_qty);
                }

                // Kurangi Stok
                $stock->decrement('stock_qty', $request->qty);

                // Catat Log Keluar
                InventoryMovement::create([
                    'inventory_stock_id' => $stock->id,
                    'type' => 'OUT',
                    'qty' => $request->qty, // Simpan positif
                    'reference_number' => 'USE/' . date('YmdHis'),
                    'notes' => $request->notes, // Misal: "Untuk kebutuhan divisi IT"
                    'created_by' => auth()->id()
                ]);
            });

            return response()->json(['message' => 'Stok berhasil dikeluarkan']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ==========================================
    // 1. Tampilkan Semua Saldo Stok (Diringkas per Barang & Gudang)
    // ==========================================
    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        // 🔥 FILTER SUPER AMAN: Kecualikan JSA/NST, tapi TETAP BAWA kode yang KOSONG (NULL)
        $allItems = \App\Models\Item::where(function($q) {
            $q->whereNotIn('item_type_code', ['JSA', 'NST'])
              ->orWhereNull('item_type_code');
        })->orderBy('name')->get();

        // 🔥 RADAR BARANG KRITIS
        $criticalStocks = \App\Models\Item::with('uom')
            ->where(function($q) {
                $q->whereNotIn('item_type_code', ['JSA', 'NST'])
                  ->orWhereNull('item_type_code');
            })
            ->whereNotNull('min_stock')
            ->where('min_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->get();

        // 🔥 QUERY DAFTAR INVENTORY (SMART TOTAL STOCK CALCULATION)
        $stocks = \App\Models\Item::query()
            ->select('items.*')
            ->with('uom')
            ->where(function($q) {
                $q->whereNotIn('item_type_code', ['JSA', 'NST'])
                  ->orWhereNull('item_type_code');
            })
            ->addSelect([
                // 1. Hitung Stok Curah (Bulk) dari inventory_stocks
                'bulk_stock' => \App\Models\InventoryStock::selectRaw('COALESCE(SUM(stock_qty), 0)')
                    ->whereColumn('item_id', 'items.id')
                    ->when($warehouseId, function($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    }),

                // 2. Hitung Stok Aset yang "Available" (Nganggur di Gudang) dari tabel fixed_assets
                'asset_stock' => \App\Models\FixedAsset::selectRaw('COUNT(id)')
                    ->whereColumn('item_id', 'items.id')
                    ->whereHas('status', function($q) {
                        $q->where('slug', 'available'); // Hanya hitung aset yang siap dipakai/dikeluarkan
                    })
                    ->when($warehouseId, function($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    })
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Menyuntikkan perhitungan "Total Stock" gabungan ke masing-masing item
        $stocks->getCollection()->transform(function ($item) {
            $item->total_stock = $item->bulk_stock + $item->asset_stock;
            return $item;
        });

        return view('inventory.index', compact('stocks', 'warehouses', 'search', 'warehouseId', 'allItems', 'criticalStocks'));
    }

    // 2. Tampilkan Kartu Stok (Riwayat Keluar Masuk) per Barang
    public function show($id)
    {
        $item = Item::where('is_stockable', true)->findOrFail($id);

        // Ambil riwayat mutasi dari yang paling baru
        $mutations = StockMutation::where('item_id', $id)
                        ->latest()
                        ->paginate(20);

        return view('inventory.show', compact('item', 'mutations'));
    }


}
