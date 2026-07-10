<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Uom;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {

        // 🔥 1. SAPU BERSIH DATA LAMA AGAR TIDAK BENTROK 🔥
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('items')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $faker = Faker::create('id_ID');

        // Tarik Master Data
        $catIds = Category::pluck('id', 'code')->toArray();
        $uomIds = Uom::pluck('id', 'code')->toArray();

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

            // 🌟 2. LOGIKA PAKSA: Masukkan data wajib terlebih dahulu agar pasti ada di database
            if (!empty($mandatoryElectronics)) {
                $type = 'STK';
                $stockCategory = 'ELK';
                $name = array_shift($mandatoryElectronics); // Ambil dan hapus satu per satu dari daftar wajib
            } else {
                // Jika daftar wajib sudah habis, gunakan acak Faker
                $type = $faker->randomElement(['STK', 'STK', 'STK', 'JSA']);
                $stockCategory = $faker->randomElement(['ATK', 'CNS', 'FNB', 'BBK', 'SPR', 'ELK', 'FNR', 'KND', 'MSN']);
            }

            if ($type === 'STK') {
                $itemTypeCode = 'STK';
                $currentStock = 0;
                $minStock = $faker->numberBetween(5, 20);
                $maxStock = $faker->numberBetween(100, 500);

                $categoryId = $catIds[$stockCategory] ?? 1;
                $code = 'SKU-' . $stockCategory . '-' . str_pad($i, 5, '0', STR_PAD_LEFT);
                $is_trackable = in_array($stockCategory, ['ELK', 'KND', 'MSN']) ? 1 : 0;

                // 🌟 3. PENAMAAN ANTI-DUPLIKAT (Disuntik nomor urut $i agar nama dijamin unik)
                if (!isset($name)) {
                    if ($stockCategory === 'ATK') {
                        $name = $faker->randomElement(['Kertas HVS A4', 'Tinta Printer Black', 'Pulpen Pilot Ballpoint']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'CNS') {
                        $name = $faker->randomElement(['Sabun Cuci Cair', 'Tisu Paseo Pack', 'Pembersih Lantai Karbol']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'FNB') {
                        $name = $faker->randomElement(['Kopi Kapal Api Sachet', 'Air Galon Aqua 19L', 'Gula Pasir Putih']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'BBK') {
                        $name = $faker->randomElement(['Beras Premium Cianjur', 'Minyak Goreng Bimoli', 'Daging Sapi Lokal']) . ' Ke-' . $i;
                    }
                    // 🔥 DI SINI KUNCI NYA: Nama ELK acak diganti total agar TIDAK SAMA dengan daftar wajib di atas
                    elseif ($stockCategory === 'ELK') {
                        $name = $faker->randomElement(['Monitor Dell 24 Inch', 'Proyektor Epson XGA', 'Switch Hub Cisco 24 Port', 'UPS Prolink 1200VA', 'Kabel LAN UTP Belden']) . ' Ke-' . $i;
                    } elseif ($stockCategory === 'KND') {
                        $name = $faker->randomElement(['Mobil Fortuner VRZ', 'Innova Zenix Hybrid', 'Motor Beat Street']) . ' Ke-' . $i;
                    } else {
                        $name = $faker->randomElement(['Ban Michelin Primacy', 'Oli Shell Helix', 'Genset Cummins Silent', 'Forklift Toyota Diesel']) . ' Ke-' . $i;
                    }
                }

                $uomId = $uomIds['PCS'] ?? $defaultUomId;

            } else {
                // JASA (SERVICE) - Disuntik nomor urut $i agar unik
                $code = 'JSA-' . str_pad($i, 4, '0', STR_PAD_LEFT);
                $categoryId = $catIds['JSA'] ?? 1;
                $itemTypeCode = 'JSA';
                $name = $faker->randomElement(['Service AC Rutin', 'Maintenance Server Tahunan', 'Jasa Konsultan IT']) . ' Ke-' . $i;
                $uomId = $uomIds['PKT'] ?? $defaultUomId;
                $is_trackable = 0;
                $currentStock = 0;
                $minStock = 0;
                $maxStock = 0;
            }

            // Gabungkan slug dengan $i agar dijamin aman
            $slug = Str::slug($name);

            DB::table('items')->insert([
                'category_id'    => $categoryId,
                'code'           => $code,
                'slug'           => $slug,
                'name'           => $name,
                'current_stock'  => $currentStock,
                'min_stock'      => $minStock,
                'max_stock'      => $maxStock,
                'is_trackable'   => $is_trackable,
                'is_active'      => 1,
                'specification'  => 'Spesifikasi detail untuk ' . $name,
                'uom_id'         => $uomId,
                'item_type_code' => $itemTypeCode,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // Hapus isi variabel name untuk loop berikutnya
            unset($name);
        }
    }
}
