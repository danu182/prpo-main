<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tambah ke tabel PR Items
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('item_id')->comment('Nama spesifik inputan user (Short Text)');
        });

        // Tambah ke tabel PO Items juga
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('item_id')->comment('Nama spesifik inputan user (Short Text)');
        });
    }

    public function down()
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });
    }
};
