<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel bill_requests
            // onDelete('cascade') artinya jika tagihan induk dihapus, riwayat bayarnya ikut terhapus
            $table->foreignId('bill_request_id')
                  ->constrained('bill_requests')
                  ->onDelete('cascade');

            $table->string('payment_number');      // Nomor Referensi Bank / Transfer
            $table->decimal('amount_paid', 15, 2); // Nominal Bayar (Max 15 digit, 2 desimal)
            $table->date('payment_date');          // Tanggal Bayar
            $table->text('note')->nullable();      // Catatan tambahan (Opsional)

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
