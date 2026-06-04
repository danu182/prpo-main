<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ItemConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $conditions = [
            [
                'name'       => 'Sesuai / Baik',
                'color'      => 'success', // Hijau
                'is_active'  => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name'       => 'Rusak / Cacat',
                'color'      => 'danger', // Merah
                'is_active'  => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name'       => 'Kuantitas Kurang',
                'color'      => 'warning', // Kuning/Oranye
                'is_active'  => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name'       => 'Salah Barang (Retur)',
                'color'      => 'secondary', // Abu-abu
                'is_active'  => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name'       => 'Kadaluarsa (Expired)',
                'color'      => 'dark', // Hitam / Gelap
                'is_active'  => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // Kosongkan tabel dulu agar tidak duplikat jika di-run berulang kali (Opsional)
        // DB::table('item_conditions')->truncate();

        DB::table('item_conditions')->insert($conditions);
    }
}
