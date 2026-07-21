<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Tambah tipe pajak dan nilai pajak di Header PO
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('global_tax_type', ['FIXED', 'PERCENT'])->nullable()->default('FIXED')->after('global_discount_value');
            $table->decimal('global_tax_value', 15, 2)->nullable()->default(0)->after('global_tax_type');
        });

        // 2. Tambah tipe pajak dan nilai pajak di Item PO
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->enum('tax_type', ['FIXED', 'PERCENT'])->nullable()->default('FIXED')->after('tax_id');
            $table->decimal('tax_value', 15, 2)->nullable()->default(0)->after('tax_type');
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['global_tax_type', 'global_tax_value']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['tax_type', 'tax_value']);
        });
    }
};
