<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Kantor Pusat (Head Office) agar AdminUserSeeder tidak error
        Company::firstOrCreate(
            ['code' => 'HO-001'],
            [
                'name' => 'PT Induk Nusantara (Head Office)',
                'address' => 'Gedung Pusat, Jakarta',
                'is_head_office' => true,
            ]
        );

        // 2. Daftar Entitas Perusahaan dari Excel Anda (Wajib Ada untuk Import)
        $excelCompanies = [
            ['name' => 'DESTINASIAN MEDIA PRIVATE', 'code' => 'DA'],
            ['name' => 'PT DAMA MAHARDIKA', 'code' => 'DM'],
            ['name' => 'PT HITAWASANA  LUHUR', 'code' => 'HL'],
            ['name' => 'PT MAHAPALA MAHARDIKA', 'code' => 'MM'],
            ['name' => 'PT PANDU DEWANATA', 'code' => 'PND'],

        ];

        foreach ($excelCompanies as $comp) {
            Company::firstOrCreate(
                ['name' => $comp['name']],
                [
                    'code' => $comp['code'],
                    'address' => 'Gedung Pusat, Lt. 5, Jl. Sudirman No. 1, Jakarta',
                    'is_head_office' => false,
                ]
            );
        }

        // Jika Anda masih ingin tambah data random dari Faker, bisa diletakkan di bawah ini
        // Namun untuk ERP fase awal, 5 perusahaan di atas sudah sangat cukup dan bersih.
    }
}
