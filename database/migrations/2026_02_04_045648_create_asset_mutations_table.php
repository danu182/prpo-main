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
        Schema::create('asset_mutations', function (Blueprint $table) {
        $table->id();

        // Relasi ke Aset yang dimutasi
        $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

        // Siapa pemegang sebelumnya (Bisa null jika aset baru)
        $table->foreignId('from_user_id')->nullable()->constrained('users');

        // Siapa pemegang baru
        $table->foreignId('to_user_id')->nullable()->constrained('users');

        // Lokasi (Bisa nama ruangan/cabang/site)
        $table->string('from_location')->nullable();
        $table->string('to_location')->nullable();

        $table->date('mutation_date');
        $table->text('reason')->nullable(); // Alasan mutasi

        // Siapa yang menyetujui mutasi ini (Manager/GA)
        $table->foreignId('approved_by')->nullable()->constrained('users');

        $table->timestamps();

    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_mutations');
    }
};
