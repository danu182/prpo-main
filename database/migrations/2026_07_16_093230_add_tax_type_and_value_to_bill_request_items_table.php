<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            // Menambahkan kolom tipe pajak (Rp / %) dan nilai inputannya
            $table->string('tax_type')->default('percent')->after('tax_id');
            $table->decimal('tax_value', 15, 2)->default(0)->after('tax_type');
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn(['tax_type', 'tax_value']);
        });
    }
};
