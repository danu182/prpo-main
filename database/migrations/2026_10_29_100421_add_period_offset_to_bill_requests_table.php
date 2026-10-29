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
        Schema::table('bill_requests', function (Blueprint $table) {
            // -1 = Bulan Lalu, 0 = Bulan Ini, 1 = Bulan Depan
            $table->tinyInteger('period_offset')->default(0)->after('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            //
        });
    }
};
