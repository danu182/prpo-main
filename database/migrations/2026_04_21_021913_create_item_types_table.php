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
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // Contoh: STK, NST, JSA
            $table->string('name');               // Contoh: Barang Stok, Non-Stok, Jasa
            $table->boolean('is_active')->default(true); // Bisa diaktifkan/dinonaktifkan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_types');
    }
};
