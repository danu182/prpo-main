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
        // 1. Tambah Qty & Harga Satuan di Item
        Schema::table('bill_items', function (Blueprint $table) {
            $table->integer('qty')->default(1)->after('description');
            $table->decimal('price', 15, 2)->default(0)->after('qty'); // Harga Satuan
            // Kolom 'amount' yang lama akan kita gunakan sebagai TOTAL BARIS (Qty * Price)
        });
    }

    public function down()
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn(['qty', 'price']);
        });
    }
};
