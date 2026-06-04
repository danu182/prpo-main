<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat tabel khusus lampiran
        Schema::create('pr_vendor_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pr_item_vendor_id'); // Relasi ke tabel purchase_request_item_vendors
            $table->string('file_name'); // Nama file asli (contoh: penawaran_pt_a.pdf)
            $table->string('file_path'); // Lokasi di storage (contoh: Transaksi/PR/.../file.pdf)
            $table->timestamps();

            // Kunci Foreign Key agar kalau vendor dihapus, filenya ikut terhapus di database
            $table->foreign('pr_item_vendor_id')
                  ->references('id')->on('purchase_request_item_vendors')
                  ->onDelete('cascade');
        });

        // 2. Buang kolom 'attachment' lama yang berupa JSON agar tabel rapi
        Schema::table('purchase_request_item_vendors', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_request_item_vendors', 'attachment')) {
                $table->dropColumn('attachment');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_vendor_attachments');
        Schema::table('purchase_request_item_vendors', function (Blueprint $table) {
            $table->text('attachment')->nullable();
        });
    }
};