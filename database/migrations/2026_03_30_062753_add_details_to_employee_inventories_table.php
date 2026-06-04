<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_inventories', function (Blueprint $table) {
            // Tambahkan kolom untuk menyimpan spesifikasi merek/warna
            $table->text('specific_details')->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_inventories', function (Blueprint $table) {
            $table->dropColumn('specific_details');
        });
    }
};
