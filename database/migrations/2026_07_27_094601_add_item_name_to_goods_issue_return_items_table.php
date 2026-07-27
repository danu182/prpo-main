<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('goods_issue_return_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('item_id');
        });
    }

    public function down()
    {
        Schema::table('goods_issue_return_items', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });
    }
};
