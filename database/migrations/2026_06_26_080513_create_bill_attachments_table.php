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
        Schema::create('bill_attachments', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel bill_requests
            $table->unsignedBigInteger('bill_request_id');

            $table->string('file_name'); // Nama asli file (Contoh: BAST.pdf)
            $table->string('file_path'); // Path penyimpanan di folder (Contoh: attachments/bills/...)

            $table->timestamps();

            // Foreign Key: Jika Tagihan dihapus, lampirannya juga akan ikut terhapus otomatis di database
            $table->foreign('bill_request_id')
                  ->references('id')
                  ->on('bill_requests') // Pastikan nama tabel induknya benar (biasanya bill_requests)
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_attachments');
    }
};
