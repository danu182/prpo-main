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
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique(); // Contoh: PAY/PT-893/2026/03/0001

            // Relasi ke Tagihan (Invoice)
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->onDelete('cascade');

            // Informasi Pembayaran
            $table->date('payment_date');
            $table->string('payment_method')->default('Transfer Bank'); // Transfer, Tunai, Cek/Giro
            $table->string('bank_name')->nullable(); // Bank pengirim (BCA, Mandiri, dll)
            $table->string('reference_number')->nullable(); // Nomor referensi transfer / No. Cek

            // Nominal yang dibayar
            $table->decimal('amount', 15, 2);

            // File bukti transfer (Opsional)
            $table->string('proof_file')->nullable();

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
        Schema::dropIfExists('vendor_payments');
    }
};
