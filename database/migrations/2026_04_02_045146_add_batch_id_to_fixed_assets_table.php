<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Menambahkan kolom batch_id dan memberikan index agar pencarian data saat cetak BAST lebih cepat
            $table->string('batch_id')->nullable()->after('supporting_document')->index();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropIndex(['batch_id']); // Hapus index dulu
            $table->dropColumn('batch_id');  // Baru hapus kolomnya
        });
    }
};
