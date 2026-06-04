<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker; // Import Faker
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // 'id_ID' agar namanya berbau Indonesia

        for($i = 1; $i <= 10; $i++){
            DB::table('companies')->insert([
                'code' => 'PT-' . $faker->unique()->numerify('###'),
                'name' => $faker->company,
                'address' => $faker->address,
                'is_head_office' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
