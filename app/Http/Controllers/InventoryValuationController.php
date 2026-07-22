<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class InventoryValuationController extends Controller
{
    public function index(Request $request)
    {
        // 1. Tangkap input filter gudang dari URL
        $selectedWarehouseId = $request->input('warehouse_id');

        // 2. Tarik daftar gudang untuk ditampilkan di Dropdown HTML
        $warehouses = Warehouse::orderBy('name')->get();

        // 3. Tarik data valuasi stok (Otomatis terfilter jika Gudang dipilih)
        $valuations = InventoryStock::with(['item.uom', 'item.category'])
            ->when($selectedWarehouseId, function ($query) use ($selectedWarehouseId) {
                // Jika user memilih gudang tertentu, filter datanya!
                return $query->where('warehouse_id', $selectedWarehouseId);
            })
            ->select(
                'item_id',
                DB::raw('SUM(stock_qty) as total_qty'),
                DB::raw('SUM(stock_qty * unit_price) as total_value')
            )
            ->where('stock_qty', '>', 0)
            ->groupBy('item_id')
            ->get();

        // 4. Hitung Grand Total Keseluruhan untuk KPI (Key Performance Indicator)
        $grandTotalValue = $valuations->sum('total_value');
        $totalItems = $valuations->count();
        $totalQtyFisik = $valuations->sum('total_qty');

        return view('reports.inventory_valuation', compact(
            'valuations',
            'grandTotalValue',
            'totalItems',
            'totalQtyFisik',
            'warehouses',
            'selectedWarehouseId'
        ));
    }

    public function print(Request $request)
    {
        // Opsional: Untuk fitur cetak PDF nanti
        return back()->with('success', 'Fitur cetak PDF sedang disiapkan!');
    }
}
