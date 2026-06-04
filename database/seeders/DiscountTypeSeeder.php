<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema; // PASTIKAN TAMBAH INI DI ATAS

class DiscountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. MATIKAN PENGECEKAN RELASI SEMENTARA
        Schema::disableForeignKeyConstraints();

        // 2. KOSONGKAN TABEL (Aman karena pengecekan mati)
        DB::table('discount_types')->truncate();
        // atau jika pakai model: \App\Models\DiscountType::truncate();

        $discounts = [
            [
                'name'       => 'Voucher Promo / Kupon',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Diskon Spesial Member',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Kompensasi Keterlambatan (Denda)',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Cashback Vendor',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'Potongan Pembayaran Tunai (Cash Discount)',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Kosongkan tabel terlebih dahulu agar tidak ada duplikat jika dijalankan ulang
        DB::table('discount_types')->truncate();

        // Masukkan data ke database
        DB::table('discount_types')->insert($discounts);
    }
}
