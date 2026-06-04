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
            // Kolom untuk No Ref Bank (Boleh kosong jika cash, tapi disarankan isi)
            $table->string('transaction_reference')->nullable()->after('payment_number');
        });
    }

    public function down()
    {
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropColumn('transaction_reference');
        });
    }
};
