<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_issue_items', function (Blueprint $table) {
            // Tambahkan kolom uom_id dan uom setelah kolom qty_issued
            $table->unsignedBigInteger('uom_id')->nullable()->after('qty_issued');
            $table->string('uom')->nullable()->after('uom_id');

            // Relasi ke tabel master uom (agar aman jika master dihapus)
            $table->foreign('uom_id')
                  ->references('id')
                  ->on('item_uoms')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->dropForeign(['uom_id']);
            $table->dropColumn(['uom_id', 'uom']);
        });
    }
};
