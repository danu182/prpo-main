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
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Menambahkan kolom invoice dan nomor rekening (Bisa kosong/null)
            $table->string('invoice_number')->nullable()->after('payment_terms');
            $table->string('account_number')->nullable()->after('invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Menghapus kolom jika di-rollback
            $table->dropColumn(['invoice_number', 'account_number']);
        });
    }
};
