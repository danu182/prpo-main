<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_charges', function (Blueprint $table) {
            $table->id();
            // Relasi ke tagihan induk (ON DELETE CASCADE agar jika tagihan dihapus, charges-nya ikut terhapus)
            $table->foreignId('bill_request_id')->constrained('bill_requests')->onDelete('cascade');

            // Relasi ke master data tipe charge
            $table->foreignId('charge_type_id')->constrained('charge_types');

            $table->decimal('amount', 15, 2)->default(0); // Nominal uang
            $table->string('note')->nullable(); // Keterangan opsional

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_charges');
    }
};
