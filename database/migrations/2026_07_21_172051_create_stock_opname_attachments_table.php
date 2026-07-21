<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_opname_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_opname_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamps();

            // Relasi (Opsional: menghapus lampiran otomatis jika dokumen SO dihapus)
            $table->foreign('stock_opname_id')
                  ->references('id')->on('stock_opnames')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_opname_attachments');
    }
};
