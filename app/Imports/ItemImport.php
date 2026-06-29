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
        // 1. Tarik master data menggunakan 'code' sebagai key (Karena template pakai Kode)
        $categories = Category::pluck('id', 'code')->mapWithKeys(function ($id, $code) {
            return [strtolower(trim($code)) => $id];
        })->toArray();

        $uoms = Uom::pluck('id', 'code')->mapWithKeys(function ($id, $code) {
            return [strtolower(trim($code)) => $id];
        })->toArray();

        DB::transaction(function () use ($rows, $categories, $uoms) {
            foreach ($rows as $row) {

                // Abaikan jika nama barang kosong
                if (empty($row['nama_barang'])) continue;

                // 2. Pencocokan ID berdasarkan KODE dari Excel
                $catCode = strtolower(trim($row['kode_kategori'] ?? ''));
                $catId   = $categories[$catCode] ?? null;

                $uomCode = strtolower(trim($row['kode_satuan_dasar'] ?? ''));
                $uomId   = $uoms[$uomCode] ?? null;

                // 3. Tangkap nilai format baru (Tipe Barang HANYA STK atau JSA)
                $itemTypeCode = strtoupper(trim($row['kode_tipe_barang_stkjsa'] ?? 'STK'));
                $isTrackable  = strtoupper(trim($row['lacak_inventaris_yn'] ?? 'N')) === 'Y' ? 1 : 0;
                $itemName     = trim($row['nama_barang']);

                // 4. Generate Kode Barang Otomatis (Jika barang adalah barang baru)
                $item = Item::where('name', $itemName)->first();
                if (!$item) {
                    $lastItem = Item::orderBy('id', 'desc')->first();
                    $nextId   = $lastItem ? $lastItem->id + 1 : 1;
                    $newCode  = 'ITM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                } else {
                    $newCode  = $item->code; // Gunakan kode lama jika barang di-update
                }

                // 5. Simpan atau Update ke Database menggunakan Nama Barang sebagai acuan
                Item::updateOrCreate(
                    ['name' => $itemName], // Acuan mencegah duplikat
                    [
                        'code'           => $newCode,
                        'category_id'    => $catId,
                        'uom_id'         => $uomId,

                        // 🔥 Format Baru Menggantikan is_stockable dan is_asset 🔥
                        'item_type_code' => $itemTypeCode,

                        'is_trackable'   => $isTrackable,
                        'min_stock'      => isset($row['stok_minimum']) ? (float) $row['stok_minimum'] : 0,
                        'max_stock'      => isset($row['stok_maksimum']) ? (float) $row['stok_maksimum'] : 0,
                        'specification'  => $row['spesifikasi_bawaan'] ?? null,
                        'is_active'      => 1, // Default langsung aktif
                    ]
                );
            }
        });
    }
}
