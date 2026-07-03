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
        Schema::table('categories', function (Blueprint $table) {
            // 🔥 MENAMBAHKAN KOLOM PARENT_ID (SELF-REFERENCE KELURGA CATEGORY) 🔥
            // Digunakan untuk konsep bertingkat: Kategori Besar (Parent) -> Tipe Barang (Child)
            if (!Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')
                      ->nullable()
                      ->after('id') // Kita letakkan tepat di bawah ID agar struktur di DBMS rapi
                      ->constrained('categories') // Relasi mengunci ke id di tabel categories itu sendiri
                      ->onDelete('cascade'); // Jika kategori induk dihapus, sub-kategori/tipe di bawahnya ikut terhapus
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
        });
    }
};
