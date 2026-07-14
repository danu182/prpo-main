<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetCategory;

class AssetCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'Kelompok 1 (Komputer, HP, Perkakas)', 'useful_life_years' => 4, 'is_active'=>1],
            ['name' => 'Kelompok 2 (AC, Mobil, Furniture Logam)', 'useful_life_years' => 8,'is_active'=>1],
            ['name' => 'Kelompok 3 (Mesin Pabrik, Alat Berat)', 'useful_life_years' => 16,'is_active'=>1],
            ['name' => 'Kelompok 4 (Bangunan Gedung Permanen)', 'useful_life_years' => 20, 'is_active'=>1],
        ];

        foreach ($categories as $cat) {
            AssetCategory::create($cat);
        }
    }
}
