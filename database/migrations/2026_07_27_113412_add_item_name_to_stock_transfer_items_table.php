<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up()
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('item_id');
        });
    }

    public function down()
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });
    }
};
