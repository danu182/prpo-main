<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            // Tambahkan kolom batas minimum dan maksimum per Gudang
            $table->decimal('min_stock', 15, 2)->nullable()->after('stock_qty');
            $table->decimal('max_stock', 15, 2)->nullable()->after('min_stock');
        });
    }

    public function down()
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
    }
};
