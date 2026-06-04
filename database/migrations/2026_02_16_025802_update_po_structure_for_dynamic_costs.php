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
        // 1. Update tabel Item untuk mengakomodasi Diskon & Edit Harga
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('discount_amount', 15, 2)->default(0)->after('unit_price'); // Diskon nominal per baris
            $table->text('notes')->nullable()->after('subtotal'); // Catatan per item
        });

        // 2. Buat Tabel Baru untuk Biaya Tambahan (Ongkir, Asuransi, dll)
        Schema::create('purchase_order_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->string('description'); // Contoh: "Ongkos Kirim JNE", "Asuransi Allianz"
            $table->decimal('amount', 15, 2); // Nominal biaya
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
