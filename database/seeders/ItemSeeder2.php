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
            // ===============================================
            // DATA LAMA BAWAAN SEEDER (6 ITEM AWAL)
            // ===============================================
            [
                'name' => 'Komputer Desktop Core i7',
                'code' => 'SKU-ELK-00001',
                'uom_id' => 2,
                'category_id' => 13,
                'item_type_code' => 'STK',
                'is_trackable' => 1,
                'current_stock' => 10,
                'specification' => 'Spesifikasi detail untuk Komputer Desktop Core i7',
            ],
            [
                'name' => 'Kendaraan Operasional Motor',
                'code' => 'SKU-CNS-00001',
                'uom_id' => 2,
                'category_id' => 5,
                'item_type_code' => 'STK',
                'is_trackable' => 1,
                'current_stock' => 2,
                'specification' => 'Spesifikasi detail untuk Kendaraan Operasional Motor',
            ],
            [
                'name' => 'Notebook Laptop',
                'code' => 'SKU-LAP-00001',
                'uom_id' => 2,
                'category_id' => 13,
                'item_type_code' => 'STK',
                'is_trackable' => 1,
                'current_stock' => 10,
                'specification' => 'Spesifikasi detail untuk Notebook Laptop',
            ],
            [
                'name' => 'Kertas HVS A4 80gr',
                'code' => 'SKU-ATK-00001',
                'uom_id' => 5,
                'category_id' => 4,
                'item_type_code' => 'STK',
                'is_trackable' => 0,
                'current_stock' => 50,
                'specification' => 'Spesifikasi detail untuk Kertas HVS A4 80gr',
            ],
            [
                'name' => 'Jasa Service AC & Maintenance',
                'code' => 'SKU-JSA-00001',
                'uom_id' => 23,
                'category_id' => 17,
                'item_type_code' => 'JSA',
                'is_trackable' => 0,
                'current_stock' => 0,
                'specification' => 'Spesifikasi detail untuk Jasa Service AC & Maintenance',
            ],
            [
                'name' => 'Konsumsi Rapat / Snack Box',
                'code' => 'SKU-FNB-00001',
                'uom_id' => 4,
                'category_id' => 6,
                'item_type_code' => 'JSA',
                'is_trackable' => 0,
                'current_stock' => 0,
                'specification' => 'Spesifikasi detail untuk Konsumsi Rapat / Snack Box',
            ],

            // ===============================================
            // DATA BARU DARI EXCEL (ATK / TINTA)
            // Asumsi: uom_id untuk PCS = 1, category_id ATK = 4
            // ===============================================
            [
                'name' => 'Tinta Refill Epson Warna Hitam',
                'code' => 'SKU-ATK-00002',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Black',
            ],
            [
                'name' => 'Tinta Refill Epson Warna Merah',
                'code' => 'SKU-ATK-00003',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Magenta',
            ],
            [
                'name' => 'Tinta Refill Epson Warna Kuning',
                'code' => 'SKU-ATK-00004',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Yellow',
            ],
            [
                'name' => 'Tinta Refill Epson Warna Biru',
                'code' => 'SKU-ATK-00005',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Cyan',
            ],
            [
                'name' => 'Tinta Refill Brother Warna Hitam',
                'code' => 'SKU-ATK-00006',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Black',
            ],
            [
                'name' => 'Tinta Refill Brother Warna Merah',
                'code' => 'SKU-ATK-00007',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Magenta',
            ],
            [
                'name' => 'Tinta Refill Brother Warna Kuning',
                'code' => 'SKU-ATK-00008',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Yellow',
            ],
            [
                'name' => 'Tinta Refill Brother Warna Biru',
                'code' => 'SKU-ATK-00009',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Cyan',
            ],
            [
                'name' => 'Toner Printer Hp',
                'code' => 'SKU-ATK-00010',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Black (Toner)',
            ],

            // ===============================================
            // DATA BARU DARI EXCEL (ELEKTRONIK / KOMPONEN)
            // Asumsi: uom_id untuk PCS = 1, category_id ELK = 13
            // ===============================================
            [
                'name' => 'Ram DDR 3',
                'code' => 'SKU-ELK-00002',
                'uom_id' => 1, 'category_id' => 13, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'DDR3',
            ],
            [
                'name' => 'Ram DDR 4',
                'code' => 'SKU-ELK-00003',
                'uom_id' => 1, 'category_id' => 13, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'DDR4',
            ],
            [
                'name' => 'Sodim DDR 3',
                'code' => 'SKU-ELK-00004',
                'uom_id' => 1, 'category_id' => 13, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'DDR3',
            ],
            [
                'name' => 'Sodim DDR 4',
                'code' => 'SKU-ELK-00005',
                'uom_id' => 1, 'category_id' => 13, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'DDR4',
            ],
            [
                'name' => 'Hard Disk SSD',
                'code' => 'SKU-ELK-00006',
                'uom_id' => 1, 'category_id' => 13, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'SSD',
            ],
            [
                'name' => 'Hard Disk NvME',
                'code' => 'SKU-ELK-00007',
                'uom_id' => 1, 'category_id' => 13, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'NvMe',
            ],
            [
                'name' => 'Hard Disk External',
                'code' => 'SKU-ELK-00008',
                'uom_id' => 1, 'category_id' => 13, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'External',
            ],

            // ===============================================
            // DATA BARU DARI EXCEL (ATK / AKSESORIS KOMPUTER)
            // ===============================================
            [
                'name' => 'Pasta Prosesor',
                'code' => 'SKU-ATK-00011',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Pasta',
            ],
            [
                'name' => 'Keyboard USB',
                'code' => 'SKU-ATK-00012',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'USB kabel',
            ],
            [
                'name' => 'Mouse USB',
                'code' => 'SKU-ATK-00013',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'USB kabel',
            ],
            [
                'name' => 'Keyboard Wireless Bluetooth',
                'code' => 'SKU-ATK-00014',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Wireless',
            ],
            [
                'name' => 'Mouse Wireless Bluetooth',
                'code' => 'SKU-ATK-00015',
                'uom_id' => 1, 'category_id' => 4, 'item_type_code' => 'STK', 'is_trackable' => 0, 'current_stock' => 0,
                'specification' => 'Wireless',
            ],
        ];

        foreach ($items as $item) {
            // 🔥 PERBAIKAN: Gunakan updateOrInsert agar tidak bentrok dengan data yang sudah ada
            DB::table('items')->updateOrInsert(
                ['code' => $item['code']], // Cari berdasarkan kode SKU ini
                [
                    'category_id'    => $item['category_id'],
                    'slug'           => Str::slug($item['name']) . '-' . Str::random(4),
                    'name'           => $item['name'],
                    'current_stock'  => $item['current_stock'],
                    'is_trackable'   => $item['is_trackable'],
                    'is_active'      => 1,
                    'specification'  => $item['specification'],
                    'uom_id'         => $item['uom_id'],
                    'item_type_code' => $item['item_type_code'],
                    // Jika data sudah ada, created_at tidak akan tertimpa
                    'updated_at'     => now(),
                ]
            );
        }
    }
}
