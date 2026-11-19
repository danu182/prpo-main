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
        Schema::table('fixed_asset_import_details', function (Blueprint $table) {
            $table->decimal('harga_beli', 18, 2)->default(0.00)->change();
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->decimal('purchase_price', 18, 2)->default(0.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_asset_import_details', function (Blueprint $table) {
            $table->decimal('harga_beli', 15, 0)->default(0)->change();
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 0)->default(0)->change();
        });
    }
};
