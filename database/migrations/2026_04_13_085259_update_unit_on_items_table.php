<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('items', function (Blueprint $table) {
            // Hapus kolom unit lama (jika ada)
            $table->dropColumn('unit');

            // Tambahkan Base UOM
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
        });
    }
    public function down() {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['uom_id']);
            $table->dropColumn('uom_id');
            $table->string('unit')->nullable();
        });
    }
};
