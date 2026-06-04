<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker; // Import Faker
use Illuminate\Support\Facades\DB;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // 'id_ID' agar namanya berbau Indonesia

        for($i = 1; $i <= 50; $i++){
            DB::table('vendors')->insert([
                'name' => $faker->company,
                'email' => $faker->companyEmail,
                'phone' => $faker->phoneNumber,
                'address' => $faker->address,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
