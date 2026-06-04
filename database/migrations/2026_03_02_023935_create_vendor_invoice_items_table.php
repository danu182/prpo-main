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
        Schema::create('vendor_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->onDelete('cascade');
            $table->foreignId('goods_receipt_item_id')->constrained('goods_receipt_items')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');

            // Kuantitas ini diambil dari GR (Penerimaan), tidak boleh diketik manual
            $table->decimal('qty_invoiced', 10, 2);

            // Harga ini diambil dari PO, tidak boleh diketik manual
            $table->decimal('price', 15, 2);

            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_items');
    }
};
