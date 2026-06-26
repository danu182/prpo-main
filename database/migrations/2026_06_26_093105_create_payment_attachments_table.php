<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_payment_id'); // Relasi ke pembayaran
            $table->string('file_name');
            $table->string('file_path');
            $table->string('description')->nullable(); // Kolom untuk Keterangan/Note file
            $table->timestamps();

            $table->foreign('bill_payment_id')->references('id')->on('bill_payments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attachments');
    }
};
