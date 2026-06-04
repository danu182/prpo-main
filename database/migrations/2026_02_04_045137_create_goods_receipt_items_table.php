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
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items');
            $table->foreignId('item_id')->constrained('items');

            $table->decimal('qty_received', 10, 2);
            $table->decimal('qty_rejected', 10, 2)->default(0);

            // PERUBAHAN: Relasi ke tabel item_conditions
            $table->foreignId('condition_id')->constrained('item_conditions');

            $table->text('notes')->nullable(); // Catatan spesifik per barang jika ada masalah


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
