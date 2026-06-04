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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->string('gr_number')->unique(); // GR/2024/XX
            $table->string('delivery_note_number')->nullable(); // No Surat Jalan Vendor
            $table->date('received_date');
            $table->foreignId('received_by')->constrained('users');

            $table->string('attachment')->nullable(); // Foto surat jalan / barang

            // Dokumen Vendor
            $table->string('delivery_order_number')->nullable(); // Surat Jalan
            $table->string('invoice_number')->nullable();

            // Note: File fisik diupload pakai Spatie Media Library, tidak butuh kolom blob di sini
            $table->text('notes')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
