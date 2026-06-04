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
            // Cek dulu biar tidak error jika dijalankan ulang
            if (!Schema::hasColumn('pr_item_vendors', 'currency')) {
                // Kita tambahkan kolom 'currency'
                // default('IDR') artinya: Data lama otomatis akan terisi 'IDR'
                // after('vendor_id') artinya: Posisi kolom ditaruh setelah vendor_id (biar rapi)
                $table->string('currency', 10)->default('IDR')->after('vendor_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_item_vendors', function (Blueprint $table) {
            if (Schema::hasColumn('pr_item_vendors', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
