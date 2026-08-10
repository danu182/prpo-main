<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Uom;
use App\Models\Warehouse; // 🔥 Wajib di-import untuk Multi-Gudang

class ItemsSeeder extends Seeder
{
    public function run(): void
    {

        // 🔥 1. SAPU BERSIH DATA LAMA AGAR TIDAK BENTROK 🔥
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('inventory_stocks')->truncate(); // Bersihkan tabel stok multi-gudang dulu
        DB::table('items')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $faker = Faker::create('id_ID');

        // Tarik Master Data
        $catIds = Category::pluck('id', 'code')->toArray();
        $uomIds = Uom::pluck('id', 'code')->toArray();
        $warehouseIds = Warehouse::pluck('id')->toArray(); // Tarik semua ID Gudang

        // Siapkan default UOM ID
        $defaultUomId = !empty($uomIds) ? array_values($uomIds)[0] : 1;

        // 🌟 1. DAFTAR BARANG ELEKTRONIK WAJIB (11 Item Pertama - Unik)
        $mandatoryElectronics = [
            'Laptop MacBook',
            'PC Windows Rakitan',
            'Desktop iMac',
            'Desktop Windows',
            'Laptop Windows',
            'Printer',
            'Server',
            'NAS',
            'Mikrotik',
            'Hand Phone',
            'Scanner',
        ];

        // Jalankan perulangan total 80 item
        for($i = 1; $i <= 80; $i++){

            // 🌟 2. LOGIKA PAKSA: Masukkan data wajib terlebih dahulu
            if (!empty($mandatoryElectronics)) {
                $type = 'STK';
                $stockCategory = 'ELK';
                $name = array_shift($mandatoryElectronics);
            } else {
                $type = $faker->randomElement(['STK', 'STK', 'STK', 'JSA']);
                $stockCategory = $faker->randomElement(['ATK', 'CNS', 'FNB', 'BBK', 'SPR', 'ELK', 'FNR', 'KND', 'MSN']);
            }

            if ($type === 'STK') {
                $itemTypeCode = 'STK';
                $categoryId = $catIds[$stockCategory] ?? 1;
                $code = 'SKU-' . $stockCategory . '-' . str_pad($i, 5, '0', STR_PAD_LEFT);
                $is_trackable = in_array($stockCategory, ['ELK', 'KND', 'MSN']) ? 1 : 0;

                // 🌟 3. PENAMAAN ANTI-DUPLIKAT
                if (!isset($name)) {
                    if ($stockCategory === 'ATK') {
                        $name = $faker->randomElement(['Kertas HVS A4', 'Tinta Printer Black', 'Pulpen Pilot Ballpoint']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'CNS') {
                        $name = $faker->randomElement(['Sabun Cuci Cair', 'Tisu Paseo Pack', 'Pembersih Lantai Karbol']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'FNB') {
                        $name = $faker->randomElement(['Kopi Kapal Api Sachet', 'Air Galon Aqua 19L', 'Gula Pasir Putih']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'BBK') {
                        $name = $faker->randomElement(['Beras Premium Cianjur', 'Minyak Goreng Bimoli', 'Daging Sapi Lokal']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'ELK') {
                        $name = $faker->randomElement(['Monitor Dell 24 Inch', 'Proyektor Epson XGA', 'Switch Hub Cisco 24 Port', 'UPS Prolink 1200VA', 'Kabel LAN UTP Belden']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'KND') {
                        $name = $faker->randomElement(['Mobil Fortuner VRZ', 'Innova Zenix Hybrid', 'Motor Beat Street']) . ' Ke-' . $i;
                    } else {
                        $name = $faker->randomElement(['Ban Michelin Primacy', 'Oli Shell Helix', 'Genset Cummins Silent', 'Forklift Toyota Diesel']) . ' Ke-' . $i;
                    }
                }

                $uomId = $uomIds['PCS'] ?? $defaultUomId;

            } else {
                // JASA (SERVICE)
                $code = 'JSA-' . str_pad($i, 4, '0', STR_PAD_LEFT);
                $categoryId = $catIds['JSA'] ?? 1;
                $itemTypeCode = 'JSA';
                $name = $faker->randomElement(['Service AC Rutin', 'Maintenance Server Tahunan', 'Jasa Konsultan IT']) . ' Ke-' . $i;
                $uomId = $uomIds['PKT'] ?? $defaultUomId;
                $is_trackable = 0;
            }

            $slug = Str::slug($name);

            // =========================================================================
            // 🔥 4. SIMPAN KE TABEL ITEMS (TANPA MIN_STOCK & MAX_STOCK) 🔥
            // Gunakan insertGetId agar kita bisa menangkap ID barang untuk dikirim ke Gudang
            // =========================================================================
            $itemId = DB::table('items')->insertGetId([
                'category_id'    => $categoryId,
                'code'           => $code,
                'slug'           => $slug,
                'name'           => $name,
                'current_stock'  => 0, // Biarkan 0, fisik aslinya ada di inventory_stocks
                'is_trackable'   => $is_trackable,
                'is_active'      => 1,
                'specification'  => 'Spesifikasi detail untuk ' . $name,
                'uom_id'         => $uomId,
                'item_type_code' => $itemTypeCode,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // =========================================================================
            // 🔥 5. SEBAR STOK KE SEMUA GUDANG (MULTI-GUDANG) 🔥
            // =========================================================================
            if ($type === 'STK' && !empty($warehouseIds)) {
                foreach ($warehouseIds as $whId) {

                    // Kita sengaja buat stok fisiknya (0-5) selalu di bawah batas minimal (10-20)
                    // Agar saat selesai di-seed, halaman Smart Restock langsung penuh merah menyala!
                    $randomMin = $faker->numberBetween(10, 20);
                    $randomMax = $faker->numberBetween(50, 100);
                    $randomQty = $faker->numberBetween(0, 5);

                    DB::table('inventory_stocks')->insert([
                        'item_id'      => $itemId,
                        'warehouse_id' => $whId,
                        'company_id'   => 1, // Asumsi ID PT default adalah 1
                        'stock_qty'    => $randomQty,
                        'min_stock'    => $randomMin,
                        'max_stock'    => $randomMax,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            // Hapus isi variabel name untuk loop berikutnya
            unset($name);
        }
    }
}
