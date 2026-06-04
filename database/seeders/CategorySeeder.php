<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // ==========================================
            // KATEGORI BARANG STOK (INVENTORY)
            // ==========================================
            ['name' => 'Alat Tulis Kantor (ATK)', 'code' => 'ATK'],
            ['name' => 'Perlengkapan Umum (Consumables)', 'code' => 'CNS'],
            ['name' => 'Makanan & Minuman (Pantry)', 'code' => 'FNB'],      // Baru: Kopi, Galon, Snack
            ['name' => 'Bahan Baku & Bumbu Dapur', 'code' => 'BBK'],        // Baru: Beras, Minyak, Garam, Daging
            ['name' => 'Suku Cadang & Onderdil (Spareparts)', 'code' => 'SPR'], // Baru: Ban, Oli, Kabel, Baut

            // ==========================================
            // KATEGORI BARANG ASET TETAP (FIXED ASSETS)
            // ==========================================
            ['name' => 'Elektronik & IT', 'code' => 'ELK'],
            ['name' => 'Furniture & Fixture', 'code' => 'FNR'],
            ['name' => 'Kendaraan & Transportasi', 'code' => 'KND'],
            ['name' => 'Mesin & Peralatan', 'code' => 'MSN'],

            // ==========================================
            // KATEGORI JASA / NON-STOK
            // ==========================================
            ['name' => 'Jasa & Layanan Profesional', 'code' => 'JSA'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['code' => $cat['code']],
                ['name' => $cat['name']]
            );
        }
    }
}
