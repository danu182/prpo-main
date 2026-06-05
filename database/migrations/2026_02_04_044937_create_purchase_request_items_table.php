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
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');

            // 🔥 PERBAIKAN: Gunakan decimal agar konsisten dan presisi 🔥
            $table->decimal('qty', 10, 2)->default(0.00);

            // User bisa menyarankan vendor & harga, tapi procurement yang putuskan nanti
            $table->foreignId('suggested_vendor_id')->nullable()->constrained('vendors');
            $table->decimal('estimated_price', 15, 2)->default(0);

            // Tracking status per item (Penting untuk Split PO)
            // 🔥 INI DIA BIANG KEROKNYA! DIUBAH DARI INTEGER KE DECIMAL 🔥
            $table->decimal('ordered_qty', 10, 2)->default(0.00);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
