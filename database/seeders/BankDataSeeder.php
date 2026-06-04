<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BankDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tambahkan baris ini di dalam public function run()
        $banks = [
            ['code' => 'BCA', 'name' => 'Bank Central Asia (BCA)'],
            ['code' => 'MANDIRI', 'name' => 'Bank Mandiri'],
            ['code' => 'BNI', 'name' => 'Bank Negara Indonesia (BNI)'],
            ['code' => 'BRI', 'name' => 'Bank Rakyat Indonesia (BRI)'],
            ['code' => 'BSI', 'name' => 'Bank Syariah Indonesia (BSI)'],
            ['code' => 'CIMB', 'name' => 'Bank CIMB Niaga'],
            ['code' => 'PERMATA', 'name' => 'Bank Permata'],
            ['code' => 'DANAMON', 'name' => 'Bank Danamon Indonesia'],
            ['code' => 'MEGA', 'name' => 'Bank Mega'],
            ['code' => 'OCBC', 'name' => 'Bank OCBC NISP'],
        ];

        foreach ($banks as $bank) {
            \App\Models\Bank::updateOrCreate(['code' => $bank['code']], $bank);
        }
    }
}
