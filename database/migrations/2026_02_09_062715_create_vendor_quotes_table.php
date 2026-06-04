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
        Schema::create('vendor_quotes', function (Blueprint $table) {
            $table->id();

            // Relasi ke Item Barang PR (One Item can have Many Quotes)
            // Pastikan nama tabel item Anda 'purchase_request_items' atau 'pr_items'. Sesuaikan jika beda.
            $table->foreignId('purchase_request_item_id')
                  ->constrained('purchase_request_items')
                  ->onDelete('cascade');

            // Relasi ke Master Vendor
            $table->foreignId('vendor_id')
                  ->constrained('vendors')
                  ->onDelete('cascade');

            // Data Penawaran
            $table->decimal('quoted_price', 15, 2)->default(0); // Harga: 123456789.00
            $table->boolean('is_selected')->default(false);     // Status terpilih atau tidak

            // Tambahan (Opsional)
            $table->text('notes')->nullable();          // Catatan vendor
            $table->string('reference_link')->nullable(); // Link toped/shopee

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_quotes');
    }
};
