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
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('restrict');

            // Opsional: Untuk mencatat dari batch mana barang ini diambil
            $table->foreignId('inventory_stock_id')->nullable()->constrained('inventory_stocks')->onDelete('set null');

            $table->double('qty_transferred');

            // Untuk mencatat SN Aset yang ikut pindah gudang
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
