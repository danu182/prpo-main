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
            // Menambahkan kolom title setelah bill_number
            // Kita buat nullable dulu agar data lama tidak error
            $table->string('title')->after('bill_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
