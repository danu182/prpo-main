<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bill_items', function (Blueprint $table) {
            // Menambahkan kolom item_id setelah bill_request_id
            $table->unsignedBigInteger('item_id')->nullable()->after('bill_request_id');
        });
    }

    public function down()
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn('item_id');
        });
    }
};
