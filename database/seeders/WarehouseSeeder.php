<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar Gudang Default Perusahaan
        $warehouses = [
            [
                'code'        => 'GDG-UTM',
                'name'        => 'Gudang Utama',
                'description' => 'Gudang pusat untuk penyimpanan barang-barang umum perusahaan.'
            ],
            [
                'code'        => 'GDG-IT',
                'name'        => 'Gudang IT',
                'description' => 'Gudang khusus penyimpanan perangkat elektronik, komputer, dan jaringan.'
            ],
            [
                'code'        => 'GDG-ATK',
                'name'        => 'Gudang ATK',
                'description' => 'Gudang khusus alat tulis kantor dan perlengkapan habis pakai harian.'
            ],
        ];

        // Looping dan masukkan ke database
        // Kita pakai updateOrCreate agar kalau dijalankan 2x tidak terjadi error duplicate/ganda
        foreach ($warehouses as $wh) {
            Warehouse::updateOrCreate(
                ['code' => $wh['code']], // Cari berdasarkan kode ini
                $wh // Jika tidak ada, buat baru. Jika ada, update datanya.
            );
        }

        $this->command->info('✅ Seeding Gudang Berhasil Dieksekusi, Komandan!');
    }
}
