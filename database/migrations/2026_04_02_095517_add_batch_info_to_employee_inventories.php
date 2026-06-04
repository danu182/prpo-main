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
        Schema::table('employee_inventories', function (Blueprint $table) {
            $table->string('batch_id')->nullable()->after('item_id');
            $table->foreignId('goods_receipt_id')->nullable()->after('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_inventories', function (Blueprint $table) {
            //
        });
    }
};
