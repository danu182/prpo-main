<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('item_serials', function (Blueprint $table) {
            // Menambahkan ID Dokumen Penerimaan (Lahir)
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            
            // Menambahkan ID Dokumen Retur (Mati/Dikembalikan)
            $table->foreignId('return_to_vendor_id')->nullable()->constrained('return_to_vendors')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('item_serials', function (Blueprint $table) {
            $table->dropForeign(['goods_receipt_id']);
            $table->dropForeign(['return_to_vendor_id']);
            $table->dropColumn(['goods_receipt_id', 'return_to_vendor_id']);
        });
    }
};