<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChargeType;

class ChargeTypeSeeder extends Seeder
{
    public function run()
    {
        $charges = [
            // Kategori: Logistik & Pengiriman
            ['category' => 'Logistik', 'name' => 'Ongkos Kirim (Delivery Fee)'],
            ['category' => 'Logistik', 'name' => 'Biaya Ekspedisi / Cargo'],
            ['category' => 'Logistik', 'name' => 'Biaya Sewa Truk (Trucking)'],

            // Kategori: Jasa & Penanganan
            ['category' => 'Jasa', 'name' => 'Biaya Bongkar Muat (Handling Fee)'],
            ['category' => 'Jasa', 'name' => 'Biaya Instalasi / Pemasangan'],
            ['category' => 'Jasa', 'name' => 'Biaya Survey Lokasi'],
            ['category' => 'Jasa', 'name' => 'Biaya Teknisi'],

            // Kategori: Packaging
            ['category' => 'Packaging', 'name' => 'Biaya Packing Kayu (Wooden Crate)'],
            ['category' => 'Packaging', 'name' => 'Biaya Packing Bubble Wrap'],
            ['category' => 'Packaging', 'name' => 'Biaya Pallet'],

            // Kategori: Asuransi & Keamanan
            ['category' => 'Asuransi', 'name' => 'Asuransi Pengiriman'],
            ['category' => 'Asuransi', 'name' => 'Asuransi Barang'],

            // Kategori: Admin & Legal
            ['category' => 'Admin', 'name' => 'Biaya Admin Bank'],
            ['category' => 'Admin', 'name' => 'Biaya Materai'],
            ['category' => 'Admin', 'name' => 'Biaya Dokumen / Legalitas'],

            // Kategori: Lain-lain
            ['category' => 'Lain-lain', 'name' => 'Biaya Lembur (Overtime Charge)'],
            ['category' => 'Lain-lain', 'name' => 'Adjustment / Pembulatan'],
        ];

        foreach ($charges as $charge) {
            ChargeType::create($charge);
        }
    }
}
