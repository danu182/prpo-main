<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            // Tambahkan kolom ini di sebelah global_discount_total
            $table->decimal('extra_discount_total', 15, 2)->default(0)->after('global_discount_total');
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
