<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {

            // 🔥 WAJIB DIHIDUPKAN KARENA MIGRATE:FRESH MEMBUAT GEMBOK INI ADA LAGI 🔥
            $table->dropForeign(['item_id']);

            // Cek di database, jika kolom ini masih ada, hapus.
            if (Schema::hasColumn('stock_adjustments', 'item_id')) {
                $table->dropColumn(['item_id', 'previous_stock', 'new_stock', 'difference']);
            }

            // Tambah Gudang
            if (!Schema::hasColumn('stock_adjustments', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->after('adjustment_number')->nullable();
            }
        });

        // Buat Tabel Detail
        if (!Schema::hasTable('stock_adjustment_items')) {
            Schema::create('stock_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_adjustment_id');
                $table->unsignedBigInteger('item_id');
                $table->decimal('previous_stock', 15, 2);
                $table->decimal('new_stock', 15, 2);
                $table->decimal('difference', 15, 2);
                $table->timestamps();
                $table->foreign('stock_adjustment_id', 'fk_adj_id')->references('id')->on('stock_adjustments')->onDelete('cascade');
            });
        }
    }
};
