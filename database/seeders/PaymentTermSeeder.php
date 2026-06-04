<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run() {
        $terms = [
            ['code' => 'COD', 'name' => 'Cash On Delivery', 'days' => 0],
            ['code' => 'NET15', 'name' => 'Net 15 Days', 'days' => 15],
            ['code' => 'NET30', 'name' => 'Net 30 Days', 'days' => 30],
            ['code' => 'NET60', 'name' => 'Net 60 Days', 'days' => 60],
            ['code' => 'DP50', 'name' => 'Down Payment 50%', 'days' => 0],
        ];
        foreach($terms as $t) \App\Models\PaymentTerm::create($t);
    }
}
