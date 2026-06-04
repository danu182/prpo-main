<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            // Tambahkan kolom 'name' setelah 'bill_request_id'
            // Gunakan string karena ini nama barang (text pendek)
            $table->string('name')->after('bill_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
