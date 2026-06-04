<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Tambahkan kolom warehouse_id tanpa strict foreign key agar data lama tidak bentrok
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('item_id');
        });
    }

    public function down()
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
    }
};
