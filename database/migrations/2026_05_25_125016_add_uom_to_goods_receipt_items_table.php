<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            // Tambahkan kolom uom tepat setelah qty_received
            $table->string('uom')->nullable()->after('qty_received');
        });
    }

    public function down()
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn('uom');
        });
    }
};