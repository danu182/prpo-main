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

    // 1. Tampilkan Semua Saldo Stok Gudang
    public function index(Request $request)
    {
        $search = $request->input('search');

        // HANYA panggil barang yang is_stockable = true
        $items = Item::where('is_stockable', true)
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.index', compact('items', 'search'));
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
