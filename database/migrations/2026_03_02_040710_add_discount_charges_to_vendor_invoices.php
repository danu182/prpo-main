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
        // Tambah kolom diskon di item tagihan
        Schema::table('vendor_invoice_items', function (Blueprint $table) {
            $table->decimal('discount_amount', 15, 2)->default(0)->after('price');
        });

        // Tambah kolom rekap biaya & diskon di header tagihan
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->decimal('item_discount_total', 15, 2)->default(0)->after('subtotal');
            $table->decimal('global_discount_total', 15, 2)->default(0)->after('tax_amount');
            $table->decimal('charge_total', 15, 2)->default(0)->after('global_discount_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            //
        });
    }
};
