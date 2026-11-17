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
        Schema::create('acquisition_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Untuk Prefix Batch: MANUAL, TEMUAN, HIBAH
            $table->string('name');           // Untuk Tampilan: Registrasi Aset Lama...
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acquisition_types');
    }
};
