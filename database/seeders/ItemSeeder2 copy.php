<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ItemSeeder2 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $items = [
            // 1. Aset: Komputer Desktop (Satuan: Unit)
            [
                'name' => 'Komputer Desktop Core i7',
                'code' => 'SKU-ELK-00001',
                'uom_id' => 2,
                'category_id' => 13,
                'item_type_code' => 'STK',
                'is_trackable' => 1,
                'current_stock' => 10,
            ],
            // 2. Aset: Motor (Satuan: Unit)
            [
                'name' => 'Kendaraan Operasional Motor',
                'code' => 'SKU-CNS-00001',
                'uom_id' => 2,
                'category_id' => 5,
                'item_type_code' => 'STK',
                'is_trackable' => 1,
                'current_stock' => 2,
            ],
            // 3. Aset: Notebook / Laptop (Satuan: Unit)
            [
                'name' => 'Notebook Laptop',
                'code' => 'SKU-LAP-00001', // 🔥 Saya tambahkan code agar tidak error null
                'uom_id' => 2,
                'category_id' => 13,
                'item_type_code' => 'STK',
                'is_trackable' => 1,
                'current_stock' => 10,
            ],
            // 4. Barang Stok: Kertas A4 (Satuan: Pack)
            [
                'name' => 'Kertas HVS A4 80gr',
                'code' => 'SKU-ATK-00001',
                'uom_id' => 5,
                'category_id' => 4,
                'item_type_code' => 'STK',
                'is_trackable' => 0,
                'current_stock' => 50,
            ],
            // 5. Jasa: Jasa Service (Satuan: Lot)
            [
                'name' => 'Jasa Service AC & Maintenance',
                'code' => 'SKU-JSA-00001',
                'uom_id' => 23,
                'category_id' => 17,
                'item_type_code' => 'JSA',
                'is_trackable' => 0,
                'current_stock' => 0,
            ],
            // 6. Non-Stok Opex: Contoh Konsumsi Rapat (Satuan: Pax/Paket)
            [
                'name' => 'Konsumsi Rapat / Snack Box',
                'code' => 'SKU-FNB-00001',
                'uom_id' => 4,
                'category_id' => 6,
                'item_type_code' => 'JSA',
                'is_trackable' => 0,
                'current_stock' => 0,
            ],
        ];

        foreach ($items as $item) {
            DB::table('items')->insert([
                'category_id'    => $item['category_id'],
                'code'           => $item['code'],
                'slug'           => Str::slug($item['name']) . '-' . Str::random(4),
                'name'           => $item['name'],
                'current_stock'  => $item['current_stock'],
                // 🔥 min_stock dan max_stock DIHAPUS DARI SINI 🔥
                'is_trackable'   => $item['is_trackable'],
                'is_active'      => 1,
                'specification'  => 'Spesifikasi detail untuk ' . $item['name'],
                'uom_id'         => $item['uom_id'],
                'item_type_code' => $item['item_type_code'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}
