<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ItemType;

class ItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'STK', 'name' => 'Barang Stok (Inventory)', 'is_active' => true],
            ['code' => 'NST', 'name' => 'Barang Non-Stok (Consumable)', 'is_active' => true],
            ['code' => 'JSA', 'name' => 'Jasa / Layanan Profesional', 'is_active' => true],
        ];

        foreach ($types as $type) {
            ItemType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'is_active' => $type['is_active']
                ]
            );
        }
    }
}
