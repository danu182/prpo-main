<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');

            // IN (Masuk dari GR/Retur) atau OUT (Keluar untuk dipakai/dijual)
            $table->enum('type', ['IN', 'OUT']);

            // Berapa jumlah yang masuk/keluar?
            $table->decimal('qty', 10, 2);

            // Saldo sebelum dan sesudah (Sangat penting untuk audit!)
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);

            // Referensi dokumen (Misal: No GR, No Permintaan Barang)
            $table->string('reference_number')->nullable();
            $table->string('notes')->nullable();

            // Siapa orang gudang yang memproses?
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
