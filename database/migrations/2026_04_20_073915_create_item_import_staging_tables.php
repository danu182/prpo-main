<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. TABEL HEADER (BATCH IMPORT)
        Schema::create('item_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique(); // Contoh: IMP-20260420-0001
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });

        // 2. TABEL DETAILS (ISI BARIS EXCEL)
        Schema::create('item_import_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_import_batch_id')->constrained()->onDelete('cascade');

            // Kolom data mentah dari Excel
            $table->string('name');
            $table->string('category_code')->nullable();
            $table->string('uom_code')->nullable();
            $table->tinyInteger('is_stockable')->default(1);
            $table->tinyInteger('is_asset')->default(0);
            $table->tinyInteger('is_trackable')->default(0);
            $table->decimal('min_stock', 15, 2)->nullable();
            $table->decimal('max_stock', 15, 2)->nullable();
            $table->text('specification')->nullable();

            // Kolom Validasi Sistem (Sangat Penting!)
            $table->boolean('is_valid')->default(true); // False jika Kategori/UOM tidak dikenali
            $table->text('validation_error')->nullable(); // Menyimpan pesan error per baris

            $table->timestamps();
        });

        // 3. TABEL LAMPIRAN (BUKTI PENDUKUNG)
        Schema::create('item_import_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_import_batch_id')->constrained()->onDelete('cascade');
            $table->string('file_name'); // Nama asli file (Brosur_Laptop.pdf)
            $table->string('file_path'); // Path penyimpanan di storage
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('item_import_attachments');
        Schema::dropIfExists('item_import_details');
        Schema::dropIfExists('item_import_batches');
    }
};
