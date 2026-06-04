<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            // Menambahkan kolom teks untuk spesifikasi/detail tambahan, diletakkan setelah item_id
            $table->text('specification')->nullable()->after('item_id');
        });
    }

    public function down()
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn('specification');
        });
    }
};