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
        // 1. Tambah kolom di purchase_order_items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->enum('discount_type', ['FIXED', 'PERCENT'])->default('FIXED'); // Baru
            $table->decimal('discount_value', 15, 2)->default(0); // Nilai input (misal: 10 atau 50000)
            // discount_amount tetap ada untuk menyimpan hasil rupiahnya
        });

        // 2. Tambah kolom di purchase_request_items untuk tracking sisa
        // Schema::table('purchase_request_items', function (Blueprint $table) {
        //     $table->decimal('qty_ordered', 10, 2)->default(0); // Berapa yg sudah jadi PO
        //     // Sisa = qty - qty_ordered
        // });

        // 3. Tambah kolom di purchase_orders untuk Global Discount
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('global_discount_type', ['FIXED', 'PERCENT'])->default('FIXED');
            $table->decimal('global_discount_value', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
