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
        // 1. Tambahkan purchase_price ke tabel Master Barang (items)
        if (Schema::hasTable('items') && !Schema::hasColumn('items', 'purchase_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('purchase_price', 15, 2)
                      ->default(0)
                      ->after('name')
                      ->comment('Harga Beli / HPP Dasar untuk Acuan');
            });
        }

        // 2. Tambahkan unit_price ke tabel Kartu Stok (inventory_stocks)
        if (Schema::hasTable('inventory_stocks') && !Schema::hasColumn('inventory_stocks', 'unit_price')) {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                $table->decimal('unit_price', 15, 2)
                      ->default(0)
                      ->after('stock_qty')
                      ->comment('Harga Perolehan per Tumpukan / Batch');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Fitur Rollback (Undo)
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'purchase_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('purchase_price');
            });
        }

        if (Schema::hasTable('inventory_stocks') && Schema::hasColumn('inventory_stocks', 'unit_price')) {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                $table->dropColumn('unit_price');
            });
        }
    }
};
