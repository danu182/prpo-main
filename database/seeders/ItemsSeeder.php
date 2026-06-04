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
        $faker = Faker::create('id_ID');

        // Tarik Master Data
        $catIds = Category::pluck('id', 'code')->toArray();
        $uomIds = Uom::pluck('id', 'code')->toArray();

        // Siapkan default UOM ID
        $defaultUomId = !empty($uomIds) ? array_values($uomIds)[0] : 1;

        for($i = 1; $i <= 80; $i++){

            // Hanya ada 2 Logika Utama di Master: STK (Fisik) dan JSA (Layanan)
            $type = $faker->randomElement(['STK', 'STK', 'STK', 'JSA']);

            if ($type === 'STK') {
                $itemTypeCode = 'STK';

                $currentStock = $faker->numberBetween(0, 150);
                $minStock = $faker->numberBetween(5, 20);
                $maxStock = $faker->numberBetween(100, 500);

                // Variasi Kategori (Dari ATK sampai Mesin)
                $stockCategory = $faker->randomElement(['ATK', 'CNS', 'FNB', 'BBK', 'SPR', 'ELK', 'FNR', 'KND', 'MSN']);
                $categoryId = $catIds[$stockCategory] ?? 1;
                $code = 'SKU-' . $stockCategory . '-' . str_pad($i, 5, '0', STR_PAD_LEFT);

                // Logika Trackable (Apakah butuh Serial Number di Gudang?)
                $is_trackable = in_array($stockCategory, ['ELK', 'KND', 'MSN']) ? 1 : 0;

                // Penamaan Dinamis
                if ($stockCategory === 'ATK') $name = $faker->randomElement(['Kertas HVS A4', 'Tinta Printer', 'Pulpen Pilot']);
                elseif ($stockCategory === 'CNS') $name = $faker->randomElement(['Sabun Cuci', 'Tisu Paseo', 'Pembersih Lantai']);
                elseif ($stockCategory === 'FNB') $name = $faker->randomElement(['Kopi Kapal Api', 'Air Galon Aqua', 'Gula Pasir']);
                elseif ($stockCategory === 'BBK') $name = $faker->randomElement(['Beras Premium', 'Minyak Goreng', 'Daging Sapi']);
                elseif ($stockCategory === 'ELK') $name = $faker->randomElement(['Laptop MacBook M3', 'PC Dell', 'Printer HP']);
                elseif ($stockCategory === 'KND') $name = $faker->randomElement(['Mobil Fortuner', 'Innova Zenix', 'Motor Beat']);
                else $name = $faker->randomElement(['Ban Michelin', 'Oli Shell', 'Genset Cummins', 'Forklift Toyota']);

                $uomId = $uomIds['PCS'] ?? $defaultUomId;

            } else {
                // JASA (SERVICE)
                $code = 'JSA-' . str_pad($i, 4, '0', STR_PAD_LEFT);
                $categoryId = $catIds['JSA'] ?? 1;
                $itemTypeCode = 'JSA';
                $name = $faker->randomElement(['Service AC', 'Maintenance Server', 'Jasa Konsultan']);
                $uomId = $uomIds['PKT'] ?? $defaultUomId;
                $is_trackable = 0;
                $currentStock = 0; $minStock = 0; $maxStock = 0;
            }

            // 🔥 SOLUSI: GABUNGKAN SLUG DENGAN VARIABEL $i AGAR DIJAMIN 1000% UNIK 🔥
            $slug = Str::slug($name) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);

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
        }
    }
}