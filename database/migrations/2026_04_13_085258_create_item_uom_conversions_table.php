<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('item_uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // Satuan Alternatif (Contoh: BOX)
            $table->foreignId('alternate_uom_id')->constrained('uoms')->cascadeOnDelete();

            // Berapa jumlah Base UOM dalam 1 Alternate UOM ini? (Contoh: 100)
            $table->decimal('conversion_rate', 15, 2);

            $table->timestamps();

            // 1 Barang tidak boleh punya setting konversi ganda untuk UOM yang sama
            $table->unique(['item_id', 'alternate_uom_id']);
        });
    }
    public function down() { Schema::dropIfExists('item_uom_conversions'); }
};
