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
        Schema::table('items', function (Blueprint $table) {
            // Menambahkan kolom min_stock dan max_stock setelah kolom current_stock (atau sesuaikan)
            $table->decimal('min_stock', 15, 2)->nullable()->after('current_stock')->comment('Batas bawah untuk peringatan stok kritis');
            $table->decimal('max_stock', 15, 2)->nullable()->after('min_stock')->comment('Batas atas untuk mencegah overstock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
    }
};
