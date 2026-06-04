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
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Menambahkan kolom company_id setelah item_id
            $table->foreignId('company_id')->nullable()->after('item_id')->constrained('companies')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
