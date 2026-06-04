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
        // Stok per Perusahaan
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('item_id')->constrained('items');
            $table->integer('stock_qty')->default(0);

            // 🔥 TEMPAT MENYIMPAN IDENTITAS BATCH (GR) 🔥
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();


        });

        // History Mutasi Stok (Kartu Stok)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_stock_id')->constrained('inventory_stocks');
            $table->string('type'); // IN (Masuk), OUT (Keluar/Pakai), ADJ (Opname)
            $table->integer('qty');
            $table->string('reference_number')->nullable(); // No GR atau No Pemakaian
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

// asssssssssssssssssssssssssssssss

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 🔥 PERBAIKAN NAMA TABEL SAAT DI-ROLLBACK 🔥
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
    }
};
