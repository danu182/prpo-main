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
        Schema::table('pr_item_vendors', function (Blueprint $table) {
            // Menambahkan kolom untuk menyimpan nama file lampiran vendor
            $table->string('attachment')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_item_vendors', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });
    }
};
