<?php

namespace App\Http\Controllers;

use App\Models\ItemSerial;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemSerialController extends Controller
{
    // ========================================================
    // 1. HALAMAN DAFTAR ASET / BARANG LACAKAN
    // ========================================================
    public function index(Request $request)
    {
        // Panggil relasi uom agar tidak terjadi error 'PCS' lagi
        $query = ItemSerial::with(['item.uom', 'warehouse', 'currentUser']);

        // Fitur Pencarian Cepat
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('serial_number', 'like', "%{$search}%")
                  ->orWhereHas('item', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                  });
        }

        $serials = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        return view('inventory.serials.index', compact('serials'));
    }

    // ========================================================
    // 2. FUNGSI SAKTI: GENERATOR SERIAL NUMBER (BISA DIPANGGIL DARI MANA SAJA)
    // ========================================================
    public static function generateSerialNumber($itemId)
    {
        $item = Item::find($itemId);
        $itemCode = $item->code ?? 'ITM';
        $yearMonth = date('Ym'); // Format: 202605
        
        $prefix = $itemCode . '-' . $yearMonth . '-';

        // Cari nomor urut terakhir di database khusus SN ini
        $lastRecord = ItemSerial::where('serial_number', 'like', "{$prefix}%")
                        ->orderBy('serial_number', 'desc')
                        ->lockForUpdate() // Kunci row agar aman saat banyak transaksi bersamaan
                        ->first();

        // Ambil 4 angka terakhir, jika belum ada, mulai dari 1
        $nextSeq = $lastRecord ? ((int) substr($lastRecord->serial_number, -4)) + 1 : 1;

        // Hasil contoh: SKU-FNB-00067-202605-0001
        return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }
}