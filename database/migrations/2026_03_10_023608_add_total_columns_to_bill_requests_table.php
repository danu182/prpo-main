<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            // Tambahkan kolom di sebelah kanan setelah kolom 'amount'
            $table->decimal('subtotal', 15, 2)->default(0)->after('amount');
            $table->decimal('total_tax', 15, 2)->default(0)->after('subtotal');
            $table->decimal('total_charge', 15, 2)->default(0)->after('total_tax');

            // Note: Kolom total_discount sudah ada di screenshot tabel Anda, jadi tidak perlu ditambah lagi.
            // Kolom 'amount' (yang sudah ada) akan menjadi Grand Total.
        });
    }

    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'total_tax', 'total_charge']);
        });
    }
};
