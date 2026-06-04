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
            $table->decimal('total_discount', 15, 2)->default(0)->after('recurring_period'); // Diskon global/keseluruhan
            // $table->decimal('grand_total', 15, 2)->default(0)->after('total_discount'); // Total akhir setelah semua pajak & diskon
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
        $table->dropColumn('total_discount');
        });
    }
};
