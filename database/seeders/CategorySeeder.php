<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // 1. BUAT KATEGORI INDUK (PARENT)
        // ==========================================
        $invParent = Category::firstOrCreate(['code' => 'INV'], ['name' => 'Barang Stok (Inventory)']);
        $astParent = Category::firstOrCreate(['code' => 'AST'], ['name' => 'Aset Tetap (Fixed Assets)']);
        $svcParent = Category::firstOrCreate(['code' => 'SVC'], ['name' => 'Jasa / Layanan (Non-Stok)']);

        // ==========================================
        // 2. BUAT ANAK KATEGORI / SUB-TIPE (CHILDREN)
        // ==========================================
        $subCategories = [
            // Anak dari Inventory
            ['name' => 'Alat Tulis Kantor (ATK)', 'code' => 'ATK', 'parent_id' => $invParent->id],
            ['name' => 'Perlengkapan Umum',       'code' => 'CNS', 'parent_id' => $invParent->id],
            ['name' => 'Makanan & Minuman',       'code' => 'FNB', 'parent_id' => $invParent->id],

            // Anak dari Aset Tetap (Sesuaikan dengan TYPE di Excel Anda)
            ['name' => 'Elektronik & IT',         'code' => 'ELK', 'parent_id' => $astParent->id],
            ['name' => 'Furniture & Fixture',     'code' => 'FNR', 'parent_id' => $astParent->id],
            ['name' => 'Laptops',                 'code' => 'LAP', 'parent_id' => $astParent->id], // Sesuai Excel
            ['name' => 'Handphone',               'code' => 'HPN', 'parent_id' => $astParent->id], // Sesuai Excel

            // Anak dari Jasa
            ['name' => 'Jasa Profesional',        'code' => 'JSA', 'parent_id' => $svcParent->id],
        ];

        foreach ($subCategories as $sub) {
            Category::firstOrCreate(
                ['code' => $sub['code']],
                ['name' => $sub['name'], 'parent_id' => $sub['parent_id']]
            );
        }
    }
}
