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
                'name' => 'Destin Asian Media Group (Head Office)',
                'address' => 'Menara Batavia, 11th Floor | Jl. K.H. Mas Mansyur Kav. 126 | Jakarta 10220 - Indonesia',
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
                    'address' => 'Menara Batavia, 11th Floor | Jl. K.H. Mas Mansyur Kav. 126 | Jakarta 10220 - Indonesia',
                    'is_head_office' => false,
                ]
            );
        }

        // Jika Anda masih ingin tambah data random dari Faker, bisa diletakkan di bawah ini
        // Namun untuk ERP fase awal, 5 perusahaan di atas sudah sangat cukup dan bersih.
    }
}
