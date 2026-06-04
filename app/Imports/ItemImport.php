<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Category;
use App\Models\Uom;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class ItemImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        // Tarik master data agar tidak bolak-balik query database (Biar cepat)
        $categories = Category::pluck('id', 'name')->mapWithKeys(function ($id, $name) { return [strtolower(trim($name)) => $id]; })->toArray();
        $uoms = Uom::pluck('id', 'code')->mapWithKeys(function ($id, $code) { return [strtolower(trim($code)) => $id]; })->toArray();

        DB::transaction(function () use ($rows, $categories, $uoms) {
            foreach ($rows as $row) {
                // Abaikan jika kode atau nama barang kosong
                if (empty($row['kode_barang']) || empty($row['nama_barang'])) continue;

                // Pencocokan ID
                $catName = strtolower(trim($row['kategori'] ?? ''));
                $catId = $categories[$catName] ?? null;

                $uomCode = strtolower(trim($row['satuan_uom'] ?? ''));
                $uomId = $uoms[$uomCode] ?? null;

                // Konversi YA / TIDAK menjadi Boolean (1 atau 0)
                $isStockable = strtoupper(trim($row['lacak_di_gudang'] ?? 'YA')) === 'YA' ? 1 : 0; // 🔥 Tambahkan tangkapan ini
                $isAsset = strtoupper(trim($row['aset_tetap'] ?? 'TIDAK')) === 'YA' ? 1 : 0;
                $isTrackable = strtoupper(trim($row['inventaris_dilacak'] ?? 'TIDAK')) === 'YA' ? 1 : 0;

                // Gunakan updateOrCreate agar bisa update data lama ATAU insert data baru
                Item::updateOrCreate(
                    ['code' => trim($row['kode_barang'])],
                    [
                        'name'          => trim($row['nama_barang']),
                        'category_id'   => $catId,
                        'uom_id'        => $uomId,
                        'is_stockable'  => $isStockable, // 🔥 Masukkan ke database
                        'is_asset'      => $isAsset,
                        'is_trackable'  => $isTrackable,
                        'min_stock'     => $row['stok_minimum'] ?: null,
                        'max_stock'     => $row['stok_maksimum'] ?: null,
                        'specification' => $row['spesifikasi_bawaan'] ?? null,
                    ]
                );
            }
        });
    }
}
