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
            $table->text('reference_link')->nullable()->after('quoted_price'); // Kolom URL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_item_vendors', function (Blueprint $table) {
            //
        });
    }
};
