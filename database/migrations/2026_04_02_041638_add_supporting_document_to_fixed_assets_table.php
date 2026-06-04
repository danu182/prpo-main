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
            // Menambahkan kolom supporting_document (Boleh kosong / nullable)
            // after('purchase_price') digunakan agar posisinya rapi di database
            $table->string('supporting_document')->nullable()->after('purchase_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Menghapus kolom jika migration di-rollback
            $table->dropColumn('supporting_document');
        });
    }
};
