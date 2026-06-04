<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migration.
     */
    public function up(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            // 1. Tambahkan kolom status_id yang berelasi ke tabel statuses
            $table->foreignId('status_id')
                  ->nullable()
                  ->after('currency') // Meletakkan kolom setelah kolom currency (opsional)
                  ->constrained('statuses')
                  ->nullOnDelete(); // Jika status di tabel master dihapus, di sini jadi NULL (tidak error)

            // 2. Hapus kolom 'status' (string) yang lama
            if (Schema::hasColumn('bill_requests', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    /**
     * Balikkan (Rollback) migration jika terjadi kesalahan.
     */
    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            // 1. Hapus relasi dan kolom status_id
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');

            // 2. Kembalikan kolom status (string) seperti semula
            $table->string('status', 50)->default('PENDING');
        });
    }
};
