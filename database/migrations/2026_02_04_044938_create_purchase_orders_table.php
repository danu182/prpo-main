<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // --- IDENTITAS DOKUMEN ---
            $table->string('po_number')->unique(); // Format: PO/2026/02/001
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->onDelete('set null'); // Link ke PR
            $table->foreignId('vendor_id')->constrained('vendors'); // Vendor Pemenang
            $table->foreignId('bill_to_company_id')->constrained('companies'); // PT yang membeli

            // --- STATUS & TANGGAL ---
            $table->string('status')->default('DRAFT'); // DRAFT, ISSUED, PARTIAL, COMPLETED, CANCELLED
            $table->date('po_date');
            $table->date('delivery_date')->nullable(); // Estimasi barang sampai

            // --- APPROVAL & LOG ---
            $table->foreignId('created_by')->constrained('users'); // Pembuat PO
            $table->foreignId('approved_by')->nullable()->constrained('users'); // Penanda tangan
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_sent')->default(false); // Penanda sudah dikirim ke vendor

            // --- DETAIL PENGIRIMAN & PEMBAYARAN ---
            $table->string('currency', 3)->default('IDR'); // IDR, USD, SGD
            $table->text('shipping_address')->nullable(); // Alamat kirim barang
            $table->string('payment_terms')->nullable(); // Net 30, COD, DP 50%
            $table->text('notes')->nullable(); // Catatan umum

            // --- FINANCIAL SUMMARY (Untuk mempermudah query report) ---
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0); // Total pajak dari semua item
            $table->decimal('grand_total', 15, 2)->default(0); // Yang harus dibayar

            $table->timestamps();
            $table->softDeletes(); // Agar data tidak hilang permanen saat dihapus
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_orders');
    }
};
