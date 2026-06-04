<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Category;

class ItemService
{
    /**
     * Generate Kode Barang Otomatis (Format: TIPE-KATEGORI-000001)
     */
    public function generateItemCode($categoryId, $itemTypeCode, $isAsset)
    {
        // 1. Ambil Data Kategori
        $category = \App\Models\Category::findOrFail($categoryId);
        $catCode = strtoupper(trim($category->code));

        // 2. Tentukan Tipe Depan berdasarkan Kode Tipe Barang
        $typePrefix = 'JSA'; // Default: Jasa

        if ($isAsset == '1') {
            $typePrefix = 'AST'; // Aset Tetap
        } elseif ($itemTypeCode === 'STK') {
            $typePrefix = 'SKU'; // Barang Persediaan (Stok)
        } elseif ($itemTypeCode === 'NST') {
            $typePrefix = 'NST'; // Barang Non-Stok (Consumable)
        }

        // Gabungkan Tipe dan Kode Kategori (Contoh: SKU-ATK-)
        $prefix = $typePrefix . '-' . $catCode . '-';

        // 3. Cari nomor urut terakhir di database berdasarkan prefix ini
        $lastItem = \App\Models\Item::where('code', 'like', $prefix . '%')
                        ->orderBy('id', 'desc')
                        ->first();

        $nextNumber = 1;

        if ($lastItem) {
            // Ekstrak angka dari kode terakhir
            $lastNumber = (int) substr($lastItem->code, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        }

        // Format jadi 6 digit urutan (Contoh: SKU-ATK-000001)
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }


    /**
     * Generate Slug Unik Anti-Bentrok
     */
    public function generateUniqueSlug($name)
    {
        // Ubah "Indomi Soto" jadi "indomi-soto"
        $slug = \Illuminate\Support\Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        // Looping ngecek database. Selama slug itu sudah dipakai (walau nonaktif), tambah angka di belakangnya!
        while (\App\Models\Item::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }



}
