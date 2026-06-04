<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Uom;

class UomSeeder extends Seeder
{
    public function run()
    {
        $uoms = [
            // ==========================================
            // 1. SATUAN DASAR (PIECES/UNIT)
            // ==========================================
            ['code' => 'PCS',  'name' => 'Pieces', 'description' => 'Satuan dasar per biji/potong/unit'],
            ['code' => 'UNIT', 'name' => 'Unit',   'description' => 'Satuan untuk mesin/kendaraan/elektronik'],
            ['code' => 'SET',  'name' => 'Set',    'description' => 'Satuan gabungan (satu set)'],

            // ==========================================
            // 2. SATUAN KEMASAN / KELOMPOK
            // ==========================================
            ['code' => 'BOX',  'name' => 'Box / Kardus', 'description' => 'Satuan kotak atau kardus besar'],
            ['code' => 'PACK', 'name' => 'Pack',         'description' => 'Satuan bungkusan/pak'],
            ['code' => 'LSN',  'name' => 'Lusin',        'description' => 'Kemasan isi 12 Pcs'],
            ['code' => 'KOD',  'name' => 'Kodi',         'description' => 'Kemasan isi 20 Pcs'],
            ['code' => 'RIM',  'name' => 'Rim',          'description' => 'Satuan kertas (isi 500 lembar)'],
            ['code' => 'SAK',  'name' => 'Karung / Sak', 'description' => 'Satuan karung (beras, semen, pupuk)'],
            ['code' => 'BTL',  'name' => 'Botol',        'description' => 'Satuan kemasan botol'],
            ['code' => 'KLG',  'name' => 'Kaleng',       'description' => 'Satuan kemasan kaleng'],
            ['code' => 'GLN',  'name' => 'Galon',        'description' => 'Satuan galon (air minum/cairan)'],

            // ==========================================
            // 3. SATUAN BERAT (MASS) -> Untuk Bahan Baku
            // ==========================================
            ['code' => 'KG',   'name' => 'Kilogram', 'description' => 'Satuan berat (1.000 gram)'],
            ['code' => 'GR',   'name' => 'Gram',     'description' => 'Satuan berat ringan'],
            ['code' => 'TON',  'name' => 'Ton',      'description' => 'Satuan berat besar (1.000 Kg)'],

            // ==========================================
            // 4. SATUAN VOLUME CAIRAN -> Untuk Bahan Baku / Sparepart
            // ==========================================
            ['code' => 'LTR',  'name' => 'Liter',      'description' => 'Satuan volume cairan (minyak, air, oli)'],
            ['code' => 'ML',   'name' => 'Mililiter',  'description' => 'Satuan volume cairan kecil'],
            ['code' => 'DRUM', 'name' => 'Drum',       'description' => 'Satuan drum besar (biasanya untuk oli/kimia)'],

            // ==========================================
            // 5. SATUAN PANJANG / DIMENSI -> Untuk Sparepart/Kabel
            // ==========================================
            ['code' => 'MTR',  'name' => 'Meter', 'description' => 'Satuan ukuran panjang'],
            ['code' => 'CM',   'name' => 'Centimeter', 'description' => 'Satuan ukuran panjang kecil'],
            ['code' => 'ROLL', 'name' => 'Roll', 'description' => 'Satuan gulungan (kabel, plastik, pita)'],

            // ==========================================
            // 6. SATUAN JASA & LAYANAN
            // ==========================================
            ['code' => 'PKT',  'name' => 'Paket', 'description' => 'Satuan borongan / jasa komplit'],
            ['code' => 'LOT',  'name' => 'Lot',   'description' => 'Satuan proyek atau lot pekerjaan'],
            ['code' => 'JAM',  'name' => 'Jam',   'description' => 'Satuan waktu (untuk sewa alat / upah tenaga)'],
            ['code' => 'BLN',  'name' => 'Bulan', 'description' => 'Satuan waktu bulanan (untuk langganan/sewa)'],
        ];

        foreach ($uoms as $uom) {
            // firstOrCreate memastikan tidak ada duplikasi data jika di-seed ulang
            Uom::firstOrCreate(
                ['code' => $uom['code']],
                [
                    'name' => $uom['name'],
                    'description' => $uom['description']
                ]
            );
        }
    }
}
