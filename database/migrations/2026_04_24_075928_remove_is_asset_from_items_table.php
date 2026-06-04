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
        Schema::table('items', function (Blueprint $table) {
            // Membuang kolom is_asset karena pengakuan aset dipindah ke Akuntansi
            if (Schema::hasColumn('items', 'is_asset')) {
                $table->dropColumn('is_asset');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Mengembalikan kolom jika di-rollback (opsional)
            $table->boolean('is_asset')->default(0)->after('is_active');
        });
    }
};
