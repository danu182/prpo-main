<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('return_to_vendor_attachments', function (Blueprint $table) {
            $table->id();
            // Relasi ke tabel RTV utama (Jika RTV dihapus, lampirannya ikut terhapus)
            $table->foreignId('return_to_vendor_id')->constrained('return_to_vendors')->cascadeOnDelete();
            
            $table->string('file_name'); // Nama asli file (Contoh: foto_rusak.jpg)
            $table->string('file_path'); // Lokasi path di server
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('return_to_vendor_attachments');
    }
};