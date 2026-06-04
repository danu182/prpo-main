<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->onDelete('cascade');

            // Status dan Peminjam pada saat log ini dibuat
            $table->string('status');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');

            $table->text('notes')->nullable(); // Keterangan (Cth: Dikembalikan karena resign)

            // Siapa admin/HRD yang memproses pergantian ini?
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_histories');
    }
};
