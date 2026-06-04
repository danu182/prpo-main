<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('bill_payments', function (Blueprint $table) {
            // Kolom enum untuk tipe pembayaran
            $table->string('payment_method')->default('TRANSFER')->after('payment_number');
            // Contoh isi: 'TRANSFER', 'CASH', 'CREDIT_CARD', 'CHEQUE'
        });
    }

    public function down()
    {
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
