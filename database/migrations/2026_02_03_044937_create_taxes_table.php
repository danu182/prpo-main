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
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama Pajak, misal: PPN, PPh 23, atau Pajak Daerah

            $table->decimal('percent', 5, 2);
            // Besaran persentase pajak, misal: 11.00 atau 12.00

            $table->date('effective_date');
            // Tanggal mulai berlakunya tarif ini.
            // Memungkinkan input pajak untuk masa depan

            $table->boolean('is_active')->default(true);
            // Status untuk mengaktifkan atau menonaktifkan jenis pajak tertentu

            $table->text('description')->nullable();
            // Catatan tambahan mengenai aturan pajak tersebut

            $table->timestamps();

            // Indexing untuk mempercepat pencarian berdasarkan tanggal berlaku dan nama
            $table->index(['name', 'effective_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('taxes');
    }
};
