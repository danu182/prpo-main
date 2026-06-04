<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('return_to_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('rtv_number')->unique()->comment('Nomor Dokumen Retur');
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete()->comment('Referensi Penerimaan Awal');
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->string('delivery_note_number')->nullable()->comment('No Surat Jalan Keluar untuk Supir');
            $table->foreignId('returned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('statuses')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('return_to_vendors');
    }
};
