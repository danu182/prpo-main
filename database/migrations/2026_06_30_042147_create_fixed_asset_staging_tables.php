<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. TABEL HEADER BATCH
        Schema::create('fixed_asset_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique(); // Cth: AST-IMP-20260630-001
            $table->unsignedBigInteger('user_id'); // Siapa yang upload
            $table->string('status')->default('draft'); // draft, pending, approved, rejected
            $table->integer('current_approval_level')->default(0);
            $table->string('file_path')->nullable(); // File Excel Asli
            $table->string('support_doc')->nullable(); // Dokumen Pendukung BAST/Invoice
            $table->timestamps();
        });

        // 2. TABEL DETAIL KARANTINA (Sesuai Kolom Excel)
        Schema::create('fixed_asset_import_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');

            // Data Mentah Excel
            $table->string('kode_barang')->nullable();
            $table->string('nama_spesifik_aset')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('label_akuntansi')->nullable();
            $table->string('nama_pt')->nullable();
            $table->string('nama_gudang')->nullable();
            $table->string('status_aset')->nullable();
            $table->string('nama_peminjam')->nullable();
            $table->string('tanggal_perolehan')->nullable();
            $table->string('mata_uang')->nullable();
            $table->string('harga_beli')->nullable();
            $table->text('spesifikasi')->nullable();
            $table->text('catatan')->nullable();

            // Status Validasi Sistem
            $table->boolean('is_valid')->default(false);
            $table->text('validation_error')->nullable();

            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('fixed_asset_import_batches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_import_details');
        Schema::dropIfExists('fixed_asset_import_batches');
    }
};
