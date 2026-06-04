<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            // Tambahkan kolom qty_returned dengan nilai default 0
            $table->decimal('qty_returned', 10, 2)->default(0)->after('qty_received');
        });
    }

    public function down()
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn('qty_returned');
        });
    }
};
