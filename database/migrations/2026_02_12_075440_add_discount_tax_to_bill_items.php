<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bill_items', function (Blueprint $table) {
            // 1. Pengaturan Diskon per Item
            $table->enum('discount_type', ['fixed', 'percent'])
                  ->default('fixed')
                  ->after('amount'); // Tipe diskon: Nominal (Rp) atau Persentase (%)

            $table->decimal('discount_value', 15, 2)
                  ->default(0)
                  ->after('discount_type'); // Angka yang diinput user

            $table->decimal('discount_amount', 15, 2)
                  ->default(0)
                  ->after('discount_value'); // Hasil nominal pemotongan per item

            // 2. Snapshot Pajak per Item
            $table->unsignedBigInteger('tax_id')
                  ->nullable()
                  ->after('discount_amount'); // Referensi ke master pajak (opsional)

            $table->decimal('tax_percent_snapshot', 5, 2)
                  ->default(0)
                  ->after('tax_id'); // Mengunci % pajak saat transaksi (misal: 11.00)

            $table->decimal('tax_amount', 15, 2)
                  ->default(0)
                  ->after('tax_percent_snapshot'); // Nilai nominal pajak hasil kalkulasi

            // 3. Kalkulasi Final per Baris
            $table->decimal('subtotal', 15, 2)
                  ->default(0)
                  ->after('tax_amount'); // (Harga Bruto - Diskon Item) + Pajak Item
        });
    }

    public function down()
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn([
                'discount_type',
                'discount_value',
                'discount_amount',
                'tax_id',
                'tax_percent_snapshot',
                'tax_amount',
                'subtotal'
            ]);
        });
    }
};
