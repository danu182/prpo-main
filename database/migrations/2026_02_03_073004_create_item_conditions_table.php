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
        Schema::create('item_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Contoh: Baik, Rusak, Retur, Kurang
            $table->string('color')->default('secondary'); // Contoh: success, danger, warning (untuk class Bootstrap)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_conditions');
    }
};
