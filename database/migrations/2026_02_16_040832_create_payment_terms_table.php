<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Contoh: NET30, COD
            $table->string('name'); // Contoh: Net 30 Days
            $table->integer('days')->default(0); // Jumlah hari jatuh tempo (untuk auto-calculate due date)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
