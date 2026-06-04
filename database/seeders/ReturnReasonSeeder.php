<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReturnReason;

class ReturnReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $reasons = [
            ['name' => 'Barang Cacat Pabrik / Rusak', 'is_active' => true],
            ['name' => 'Spesifikasi Tidak Sesuai PO', 'is_active' => true],
            ['name' => 'Recall dari Pabrik (Ditarik)', 'is_active' => true],
            ['name' => 'Mendekati / Melewati Expired Date', 'is_active' => true],
            ['name' => 'Kelebihan Kirim dari Vendor', 'is_active' => true],
            ['name' => 'Kemasan / Packing Rusak Parah', 'is_active' => true],
            ['name' => 'Salah Kirim Item / Tipe', 'is_active' => true],
        ];

        foreach ($reasons as $reason) {
            // Menggunakan firstOrCreate agar data tidak duplikat jika seeder dijalankan 2x
            ReturnReason::firstOrCreate(
                ['name' => $reason['name']],
                ['is_active' => $reason['is_active']]
            );
        }

        $this->command->info('Master Data Alasan Retur (Return Reasons) berhasil disemai! 🌱');
    }
}
