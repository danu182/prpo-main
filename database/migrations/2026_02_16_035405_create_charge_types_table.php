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
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Contoh: "Ongkos Kirim", "Packing Kayu"
            $table->string('category')->nullable(); // Logistik, Admin, Pajak, dll
            $table->boolean('is_active')->default(true); // Agar bisa dinonaktifkan jika tidak dipakai lagi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_types');
    }
};
