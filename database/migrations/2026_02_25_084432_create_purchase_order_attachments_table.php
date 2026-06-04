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
        Schema::create('purchase_order_attachments', function (Blueprint $table) {
            $table->id();
            // Relasi ke tabel purchase_orders (otomatis terhapus jika PO dihapus)
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');

            $table->string('file_name'); // Menyimpan nama asli file (contoh: Penawaran_A.pdf)
            $table->string('file_path'); // Menyimpan lokasi file di server (contoh: po_attachments/xyz123.pdf)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_attachments');
    }
};
