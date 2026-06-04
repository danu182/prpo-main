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
        Schema::table('purchase_requests', function (Blueprint $table) {
            // Karena tabel mungkin sudah ada datanya, kita set nullable() dulu
            // atau berikan default value agar tidak error saat di-migrate
            $table->date('need_date')->nullable()->after('request_date');
        });
    }

    public function down()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('need_date');
        });
    }
};
