<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_request_id')->constrained('bill_requests')->onDelete('cascade');

            // Relasi ke master data tipe diskon
            $table->foreignId('discount_type_id')->constrained('discount_types');

            $table->decimal('amount', 15, 2)->default(0); // Nominal potongan harga
            $table->string('note')->nullable(); // Keterangan tambahan

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_discounts');
    }
};
