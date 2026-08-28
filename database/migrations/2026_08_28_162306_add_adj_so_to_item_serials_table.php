<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('item_serials', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_adjustment_id')->nullable();
            $table->unsignedBigInteger('stock_opname_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('item_serials', function (Blueprint $table) {
            $table->dropColumn(['stock_adjustment_id', 'stock_opname_id']);
        });
    }
};
