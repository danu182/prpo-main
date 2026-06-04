<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tax;

class TaxSeeder extends Seeder
{
    public function run()
    {
        $taxes = [
            // Skenario PPN (Pajak Pertambahan Nilai)
            [
                'name' => 'PPN',
                'percent' => 10.00,
                'effective_date' => '2021-01-01',
                'is_active' => true,
                'description' => 'Tarif PPN lama (10%)'
            ],
            [
                'name' => 'PPN',
                'percent' => 11.00,
                'effective_date' => '2022-04-01',
                'is_active' => true,
                'description' => 'Tarif PPN berlaku sejak April 2022'
            ],
            [
                'name' => 'PPN',
                'percent' => 12.00,
                'effective_date' => '2027-01-01',
                'is_active' => true,
                'description' => 'Rencana kenaikan PPN menjadi 12%'
            ],

            // Skenario PPh (Pajak Penghasilan)
            [
                'name' => 'PPh 23 (Jasa)',
                'percent' => 2.00,
                'effective_date' => '2020-01-01',
                'is_active' => true,
                'description' => 'Potongan PPh Pasal 23 untuk Jasa'
            ],
            [
                'name' => 'PPh 4 ayat 2 (Sewa)',
                'percent' => 10.00,
                'effective_date' => '2020-01-01',
                'is_active' => true,
                'description' => 'Pajak Final Sewa Bangunan/Tanah'
            ],
        ];

        foreach ($taxes as $tax) {
            Tax::create($tax);
        }
    }
}
