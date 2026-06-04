<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
        ['name' => 'Transfer Bank BCA', 'require_reference' => true],
        ['name' => 'Transfer Bank Mandiri', 'require_reference' => true],
        ['name' => 'Kas Tunai (Petty Cash)', 'require_reference' => false], // Tunai tidak wajib ref
        ['name' => 'Kartu Kredit Perusahaan', 'require_reference' => true],
        ['name' => 'Cek / Giro', 'require_reference' => true],
    ];

    foreach ($methods as $method) {
        PaymentMethod::create($method);
    }
    }
}
