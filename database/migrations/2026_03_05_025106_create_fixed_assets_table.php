<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number')->unique(); // Barcode unik sistem (AST/...)

            // Relasi ke Master Barang (Katalog)
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');

            // Relasi ke dokumen Penerimaan (GR) agar tahu asal-usulnya
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->onDelete('set null');

            $table->string('serial_number')->nullable(); // Serial Number asli dari pabrik/vendor (Bisa diisi nanti)

            // Status Aset Tetap
            $table->enum('status', ['Available', 'In Use', 'Maintenance', 'Disposed'])->default('Available');

            // Relasi ke User (Siapa yang sedang pakai laptop/mobil ini?)
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
