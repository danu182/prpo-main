<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            // 1. Tambahkan kolom uom_id (nullable, diposisikan sebelum kolom teks 'uom')
            $table->unsignedBigInteger('uom_id')->nullable()->before('uom');

            // 2. Buat relasi Foreign Key ke tabel item_uoms
            // Menggunakan onDelete('set null') agar jika data master UOM dihapus,
            // histori GR tidak ikut terhapus atau error, melainkan ID-nya saja yang jadi NULL.
            $table->foreign('uom_id')
                  ->references('id')
                  ->on('item_uoms')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            // Hapus foreign key terlebih dahulu
            $table->dropForeign(['uom_id']);

            // Hapus kolom
            $table->dropColumn('uom_id');
        });
    }
};
