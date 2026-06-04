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
            // Tambahkan kolom untuk keperluan akuntansi / aset lama
            $table->date('acquisition_date')->nullable()->after('accounting_asset_number')->comment('Tanggal Perolehan Aset');
            $table->decimal('purchase_price', 15, 2)->nullable()->after('acquisition_date')->comment('Nilai Perolehan (Harga Beli)');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['acquisition_date', 'purchase_price']);
        });
    }
};
