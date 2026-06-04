<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('goods_issues', function (Blueprint $table) {
            // Langsung tambahkan status_id tanpa perlu drop kolom lama
            $table->foreignId('status_id')->nullable()->after('gi_number')->constrained('statuses')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('goods_issues', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};
