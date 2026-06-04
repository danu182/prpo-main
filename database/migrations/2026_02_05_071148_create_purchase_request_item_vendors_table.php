<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_item_vendors', function (Blueprint $table) {
            $table->id();
            // Relasi ke Item PR (Jika item dihapus, vendor penawaran ikut terhapus)
            $table->foreignId('pr_item_id')->constrained('purchase_request_items')->cascadeOnDelete();

            // Relasi ke Master Vendor
            $table->foreignId('vendor_id')->constrained('vendors');

            $table->decimal('quoted_price', 15, 2)->default(0); // Harga Penawaran
            $table->text('reference_link')->nullable(); // Link Referensi (Tokped/Shopee/Web)
            $table->text('notes')->nullable(); // Catatan tambahan
            $table->boolean('is_selected')->default(false); // Nanti dipakai saat Approval (memilih vendor mana)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_item_vendors');
    }
};
