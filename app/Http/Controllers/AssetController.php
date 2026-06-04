<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item; // Pastikan Model Item dipanggil
use App\Imports\AssetsImport;
use Excel;
use App\Exports\AssetsTemplateExport;


class AssetController extends Controller
{
    public function index(Request $request)
    {
        // Tangkap inputan pencarian
        $search = $request->input('search');

        // Ambil data barang beserta urutannya, dan filter jika ada pencarian
        $items = Item::when($search, function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(10); // Gunakan Paginasi 10 baris

        return view('assets.index', compact('items'));
    }

    public function show($id)
    {
        // Cari barang berdasarkan ID
        $item = Item::findOrFail($id);

        // Ambil riwayat kartu stok (mutasi) khusus untuk barang ini
        // Urutkan dari yang terbaru ke terlama
        $mutations = \App\Models\StockMutation::with('creator')
                        ->where('item_id', $id)
                        ->orderBy('created_at', 'desc')
                        ->paginate(15);

        return view('assets.show', compact('item', 'mutations'));
    }


    public function showImportForm()
    {
        return view('assets.import'); // Kita akan buat file ini
    }


    public function downloadTemplate()
    {
        return Excel::download(new AssetsTemplateExport, 'Template_Import_Aset.xlsx');
    }



    // TAMBAHKAN FUNGSI BARU INI UNTUK MENERIMA UPLOAD EXCEL:
    public function import(Request $request)
    {
        // 1. Validasi file harus berupa excel/csv dan maksimal 2MB
        $request->validate([
            'import_file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ], [
            'import_file.required' => 'Pilih file Excel terlebih dahulu!',
            'import_file.mimes'    => 'Format file harus .xlsx, .xls, atau .csv',
        ]);

        try {
            // 2. Eksekusi proses import
            Excel::import(new AssetsImport, $request->file('import_file'));

            return back()->with('success', 'Data Aset berhasil diimpor & disinkronisasi!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengimpor data! Pastikan format Excel sesuai. (Error: ' . $e->getMessage() . ')');
        }
    }


}
