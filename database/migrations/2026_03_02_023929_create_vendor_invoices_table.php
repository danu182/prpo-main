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
        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // Contoh: INV/PT-988/2026/02/0001

            // Relasi ke Dokumen Sebelumnya
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->onDelete('cascade');

            // Relasi Entitas
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('restrict');
            $table->foreignId('company_id')->comment('Perusahaan yang harus membayar')->constrained('companies')->onDelete('restrict');

            // Informasi Penagihan
            $table->string('vendor_invoice_number')->nullable()->comment('Nomor faktur fisik dari cetakan vendor');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Nominal
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            // Status Tagihan: DRAFT, POSTED (Siap Bayar), PARTIAL, PAID, CANCELED
            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_invoices');
    }
};
