<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    /**
     * Menampilkan daftar Kategori Aset
     */
    public function index()
    {
        // Mengambil semua data kategori, diurutkan berdasarkan umur paling pendek
        $categories = AssetCategory::orderBy('useful_life_years', 'asc')->get();

        return view('asset_categories.index', compact('categories'));
    }

    /**
     * Menyimpan data Kategori Aset baru (Dari Modal Tambah)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'useful_life_years' => 'required|integer|min:1',
        ]);

        // Tangkap semua request, lalu paksa nilai is_active menjadi true/false berdasarkan checkbox
        $data = $request->all();
        $data['is_active'] = $request->has('is_active');

        AssetCategory::create($data);

        return redirect()->route('asset-categories.index')
                         ->with('success', 'Kategori Aset berhasil ditambahkan!');
    }

    public function update(Request $request, AssetCategory $assetCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'useful_life_years' => 'required|integer|min:1',
        ]);

        // Tangkap semua request, lalu paksa nilai is_active menjadi true/false berdasarkan checkbox
        $data = $request->all();
        $data['is_active'] = $request->has('is_active');

        $assetCategory->update($data);

        return redirect()->route('asset-categories.index')
                         ->with('success', 'Kategori Aset berhasil diperbarui!');
    }

    /**
     * Menghapus data Kategori Aset
     */
    public function destroy(AssetCategory $assetCategory)
    {
        // Fitur Opsional: Proteksi agar kategori tidak bisa dihapus jika sedang dipakai oleh aset.
        // if ($assetCategory->assets()->count() > 0) {
        //     return back()->with('error', 'Gagal! Kategori ini sedang digunakan oleh aset.');
        // }

        $assetCategory->delete();

        return redirect()->route('asset-categories.index')
                         ->with('success', 'Kategori Aset berhasil dihapus!');
    }
}
