<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number')->unique(); // Contoh: ADJ/2026/03/0001
            $table->date('adjustment_date');

            // Barang apa yang disesuaikan?
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');

            // Rekam jejak angkanya
            $table->decimal('previous_stock', 10, 2); // Stok di komputer sebelumnya
            $table->decimal('new_stock', 10, 2);      // Stok fisik aktual (real)
            $table->decimal('difference', 10, 2);     // Selisihnya (Bisa + atau -)

            $table->string('reason'); // Alasan: "Barang rusak dimakan tikus", dll.

            // Siapa yang melakukan opname?
            $table->foreignId('adjusted_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
