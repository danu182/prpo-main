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
        Schema::table('fixed_asset_import_details', function (Blueprint $table) {
            $table->string('kategori_aset')->nullable()->after('nama_spesifik_aset');
        });
    }

    public function down()
    {
        Schema::table('fixed_asset_import_details', function (Blueprint $table) {
            $table->dropColumn('kategori_aset');
        });
    }


};
