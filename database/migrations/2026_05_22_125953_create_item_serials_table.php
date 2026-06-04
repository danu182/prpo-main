<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('item_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            
            // Kunci Utama: Nomor Seri Unik (KTP Barang)
            $table->string('serial_number')->unique();
            
            // Status Barang: AVAILABLE, IN_USE, BROKEN, LOST
            $table->string('status')->default('AVAILABLE'); 
            
            // Siapa yang sedang memegang barang ini (Bisa diganti employee_id jika Komandan punya tabel HR terpisah)
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete(); 
            
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('item_serials');
    }
};