<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // <--- INI YANG BENAR

class UsageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // UsageController.php

    public function storeUsage(Request $request)
    {
        DB::transaction(function () use ($request) {

            // 1. Cari Stok yang tersedia
            $stock = InventoryStock::where('company_id', auth()->user()->company_id)
                        ->where('item_id', $request->item_id)
                        ->firstOrFail();

            if ($stock->stock_qty < $request->qty) {
                abort(400, 'Stok tidak mencukupi');
            }

            // 2. Kurangi Stok Fisik
            $stock->decrement('stock_qty', $request->qty);

            // 3. Catat History Pergerakan (PENTING)
            InventoryMovement::create([
                'inventory_stock_id' => $stock->id,
                'type' => 'OUT', // Barang Keluar
                'qty' => $request->qty, // Simpan sebagai angka positif atau negatif tergantung selera, biasanya positif tapi type=OUT
                'reference_number' => 'USE-'.date('YmdHis'), // Atau No Tiket Request
                'notes' => 'Pemakaian oleh Divisi IT',
                'created_by' => auth()->id()
            ]);

        });
    }


    public function updateRole(Request $request, $id)
    {
        $user = \App\Models\User::findOrFail($id);

        // FUNGSI SAKTI SPATIE: Menetapkan role ke user.
        // Jika sebelumnya user adalah Staff, lalu di-update jadi Manager, Spatie akan mengurus perpindahannya otomatis.
        $user->syncRoles($request->role);

        return redirect()->back()->with('success', 'Jabatan pengguna berhasil diubah!');
    }

}
