<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tambah Gudang di tabel Stok
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('item_id')->constrained('warehouses')->onDelete('cascade');
        });

        // Tambah Gudang di tabel Mutasi / Movement
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('inventory_stock_id')->constrained('warehouses')->onDelete('cascade');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('item_id')->constrained('warehouses')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
