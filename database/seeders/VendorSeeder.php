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
                'name' => 'Helios',
                'email' => 'info@helios.com',
                'phone' => '021',
                'address' => 'Jakarta',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'INDIBIZ',
                'email' => 'info@indibiz.com',
                'phone' => '021',
                'address' => 'Jakarta',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'INDIHOME',
                'email' => 'info@indihome.com',
                'phone' => '021',
                'address' => 'Jakarta',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT. Artha Telekomindo',
                'email' => 'ebilling@arthatel.co.id',
                'phone' => '021',
                'address' => 'Jakarta',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT Spectra Solusi Global ',
                'email' => 'INQUIRY@SPECTRASOLUSI.CO.ID',
                'phone' => '62 21 2284 6365',
                'address' => 'Jakarta',
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
        $faker = Faker::create('id_ID');

        // 3. Looping Faker DILUAR foreach agar hanya membuat tepat 50 data random
        for ($i = 1; $i <= 50; $i++) {
            DB::table('vendors')->insert([
                'name'       => $faker->company,
                'email'      => $faker->companyEmail,
                'phone'      => $faker->phoneNumber,
                'address'    => $faker->address,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
