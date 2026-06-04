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
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Menambahkan kolom 'name' setelah kolom 'goods_receipt_id'
            // nullable() wajib agar data aset yang sudah ada sebelumnya tidak error
            $table->string('name')->nullable()->after('goods_receipt_id')->comment('Nama spesifik hasil override dari PO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Menghapus kolom 'name' jika kita melakukan rollback
            $table->dropColumn('name');
        });
    }
};
