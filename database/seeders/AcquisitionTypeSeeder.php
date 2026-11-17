<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AcquisitionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        \App\Models\AcquisitionType::insert([
            ['code' => 'MNL', 'name' => 'Registrasi Aset Lama (Manual)', 'is_active' => true],
            ['code' => 'TEM', 'name' => 'Temuan Gudang (Belum Tercatat)', 'is_active' => true],
            ['code' => 'HIB',  'name' => 'Hibah (Pemberian Pihak Lain)', 'is_active' => true],
            ['code' => 'HAD', 'name' => 'Hadiah / Reward / Bonus', 'is_active' => true],
        ]);
    }
}
