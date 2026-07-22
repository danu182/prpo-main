<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\DB;

class InventoryValuationController extends Controller
{
    public function index(Request $request)
    {
        // Tarik data valuasi dikelompokkan berdasarkan Item (Barang)
        $valuations = InventoryStock::with(['item.uom', 'item.category'])
            ->select(
                'item_id',
                DB::raw('SUM(stock_qty) as total_qty'),
                DB::raw('SUM(stock_qty * unit_price) as total_value')
            )
            ->where('stock_qty', '>', 0)
            ->groupBy('item_id')
            ->get();

        // Hitung Grand Total Keseluruhan
        $grandTotalValue = $valuations->sum('total_value');
        $totalItems = $valuations->count();
        $totalQtyFisik = $valuations->sum('total_qty');

        return view('reports.inventory_valuation', compact('valuations', 'grandTotalValue', 'totalItems', 'totalQtyFisik'));
    }

    public function print(Request $request)
    {
        // Opsional: Untuk fitur cetak PDF nanti
        // Bisa menggunakan domPDF seperti modul lainnya
        return back()->with('success', 'Fitur cetak PDF sedang disiapkan!');
    }
}
