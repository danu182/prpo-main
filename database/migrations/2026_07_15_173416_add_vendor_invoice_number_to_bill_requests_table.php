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
        Schema::table('bill_requests', function (Blueprint $table) {
            // Menambahkan kolom nomor invoice vendor tepat setelah nama vendor
            $table->string('vendor_invoice_number')->nullable()->after('vendor_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            $table->dropColumn('vendor_invoice_number');
        });
    }
};
