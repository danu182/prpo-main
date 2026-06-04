<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('item_uoms', function (Blueprint $table) {
            $table->id();

            // Relasi ke Master Barang (Jika barang dihapus, kamus satuannya ikut terhapus otomatis)
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // Nama Kemasan (Contoh: "Kardus Isi 40", "Lusin", "Renceng")
            $table->string('uom_name');

            // Nilai Konversi ke Satuan Dasar (Contoh: 40, 12, 10).
            // Pakai desimal agar bisa support berat/volume (Contoh: Jerigen 1.5 Liter)
            $table->decimal('conversion_qty', 10, 2);

            // Flagging (Penanda) Default
            $table->boolean('is_default_purchase')->default(false)->comment('Satuan default saat buat PO');
            $table->boolean('is_default_issue')->default(false)->comment('Satuan default saat orang lapangan minta barang (GI)');

            // Optional: Jika 1 Kardus punya barcode sendiri yang beda dengan eceran
            $table->string('barcode')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('item_uoms');
    }
};
