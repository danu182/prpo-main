<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $excelVendors = [
            [
                'name' => 'TOKOPEDIA',
                'email' => 'tokopedia@tokopedia.com',
                'phone' => '021',
                'address' => 'Jakarta',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SHOPEE',
                'email' => 'shopee@shopee.com',
                'phone' => '021',
                'address' => 'Jakarta',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT. Helios Informatika Nusantara (Helios)',
                'email' => 'info@helios.com',
                'phone' => '(021) 80622220',
                'address' => 'Centennial Tower, Jl. Gatot Subroto No.Kav. 24-25 12th Floor, RT.2/RW.2, Karet Semanggi, Kecamatan Setiabudi, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12930',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT. Artha Telekomindo',
                'email' => 'ebilling@arthatel.co.id',
                'phone' => '(021) 25525100',
                'address' => 'Control Building, Sudirman, SCBD, Jl. Jend. Sudirman kav 52-53, RT.5/RW.3, Senayan, Central Business District, South Jakarta City, Jakarta 12190',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT Spectra Solusi Global',
                'email' => 'INQUIRY@SPECTRASOLUSI.CO.ID',
                'phone' => '62 21 2284 6365',
                'address' => 'MTH Square Office & Apartment, Jl. Letjen M.T. Haryono No.Kav. 10 GF A4A, RT.6/RW.12, Cawang, Jakarta Timur, Kota Jakarta Timur, Daerah Khusus Ibukota',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT. Telekomunikasi Indonesia, Tbk (Telkom)',
                'email' => 'IndibizID.care@Telkom.co.id',
                'phone' => '1500250 ext. 2',
                'address' => 'Kawasan The Telkom Hub, Gedung Telkom Landmark Tower II, lantai.39, Jl. Jenderal Gatot Subroto Kav. 52, Kuningan Barat, Mampang Prapatan, Jakarta Selatan, Jakarta, Indonesia 12710, Indonesia.',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gopgle',
                'email' => '',
                'phone' => '',
                'address' => 'Google',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT. FLEXINDO MANDIRI UTAMA',
                'email' => 'kamto.finance@flexindo.co.id',
                'phone' => '(021) 80615588',
                'address' => 'Jl. Gading Kirana Utara Kelurahan No.30 Blk E10, RT.11/RW.8, Klp. Gading Bar., Kec. Klp. Gading, Jkt Utara, Daerah Khusus Ibukota Jakarta 14240',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT. Tujuh Ion Indonesia (Indowebsite)',
                'email' => 'info@7ion.co.id',
                'phone' => '(021) 50986155',
                'address' => 'Ruko Bekasi Regensi 2 Blok ii 1 No 3-5, Cibitung 17520, Bekasi — Jawa Barat',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // 1. Looping data array yang benar ($excelVendors)
        foreach ($excelVendors as $vendorData) {
            Vendor::firstOrCreate(
                ['name' => $vendorData['name']], // Cek apakah nama vendor sudah ada
                [
                    'email'      => $vendorData['email'],
                    'phone'      => $vendorData['phone'],
                    'address'    => $vendorData['address'],
                    'is_active'  => $vendorData['is_active'],
                    'created_at' => clone $vendorData['created_at'],
                    'updated_at' => clone $vendorData['updated_at'],
                ]
            );
        } // <-- Pastikan kurung kurawal foreach ditutup di sini!

        // 2. Inisialisasi Faker (Bisa ditambahkan 'id_ID' agar datanya ala Indonesia)
        // $faker = Faker::create('id_ID');

        // 3. Looping Faker DILUAR foreach agar hanya membuat tepat 50 data random
        // for ($i = 1; $i <= 50; $i++) {
        //     DB::table('vendors')->insert([
        //         'name'       => $faker->company,
        //         'email'      => $faker->companyEmail,
        //         'phone'      => $faker->phoneNumber,
        //         'address'    => $faker->address,
        //         'is_active'  => 1,
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ]);
        // }
    }
}
