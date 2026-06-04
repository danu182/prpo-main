<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('goods_issue_returns', function (Blueprint $table) {
            // Tambahkan kolom warehouse_id setelah goods_issue_id
            $table->foreignId('warehouse_id')->nullable()->after('goods_issue_id')->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('goods_issue_returns', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
