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
    Schema::table('purchase_request_items', function (Blueprint $table) {
        // Menambahkan kolom uom_id setelah kolom qty
        $table->unsignedBigInteger('uom_id')->nullable()->after('qty');
    });
}

public function down()
{
    Schema::table('purchase_request_items', function (Blueprint $table) {
        $table->dropColumn('uom_id');
    });
}
};
