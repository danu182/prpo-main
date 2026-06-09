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
        Schema::table('return_to_vendor_items', function (Blueprint $table) {
            $table->string('uom')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_to_vendor_items', function (Blueprint $table) {
            //
        });
    }
};
