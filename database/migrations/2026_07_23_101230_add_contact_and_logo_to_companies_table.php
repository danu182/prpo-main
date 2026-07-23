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
        Schema::table('companies', function (Blueprint $table) {
            // Menambahkan kolom baru setelah kolom 'address'
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('email', 100)->nullable()->after('phone');
            $table->string('tax_id', 100)->nullable()->after('email'); // NPWP
            $table->string('logo_path', 255)->nullable()->after('tax_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Menghapus kolom jika di-rollback
            $table->dropColumn(['phone', 'email', 'tax_id', 'logo_path']);
        });
    }
};
