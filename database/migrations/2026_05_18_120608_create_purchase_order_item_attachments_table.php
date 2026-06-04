<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_order_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamps();

            // Relasi hapus otomatis jika item PO dihapus
            $table->foreign('purchase_order_item_id', 'fk_po_item_attachments')
                  ->references('id')->on('purchase_order_items')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_item_attachments');
    }
};