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
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Menambahkan kolom Tanggal Pembelian
            $table->date('purchase_date')->nullable()->after('purchase_price');

            // Menambahkan relasi ke tabel Kategori Aset
            $table->unsignedBigInteger('asset_category_id')->nullable()->after('id');

            // (Opsional) Mengunci relasi Foregin Key
            $table->foreign('asset_category_id')->references('id')->on('asset_categories')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropForeign(['asset_category_id']);
            $table->dropColumn(['purchase_date', 'asset_category_id']);
        });
    }
};
