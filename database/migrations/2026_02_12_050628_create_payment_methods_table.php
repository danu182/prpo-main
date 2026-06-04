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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // Contoh: Transfer Bank BCA, Tunai, OVO
            $table->string('code')->nullable();  // Contoh: TRF-BCA, CASH (Opsional)
            $table->boolean('require_reference')->default(true); // Fitur Pintar: Apakah wajib isi No Ref?
            $table->boolean('is_active')->default(true); // Agar bisa dimatikan jika tidak dipakai
            $table->timestamps();
        });

        // Update tabel bill_payments untuk relasi ke tabel ini
        Schema::table('bill_payments', function (Blueprint $table) {
            // Hapus kolom lama jika ada, ganti dengan foreign ID
            if (Schema::hasColumn('bill_payments', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('payment_number')
                ->constrained('payment_methods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
