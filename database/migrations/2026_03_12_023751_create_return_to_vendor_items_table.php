<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('return_to_vendor_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_to_vendor_id')->constrained('return_to_vendors')->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained()->restrictOnDelete()->comment('Item GR mana yang diretur');
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete()->comment('Untuk memulihkan jatah PO & Potong Tagihan');
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_returned', 15, 2);
            $table->string('return_reason')->comment('Alasan: Cacat, Expired, Recall');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('return_to_vendor_items');
    }
};
