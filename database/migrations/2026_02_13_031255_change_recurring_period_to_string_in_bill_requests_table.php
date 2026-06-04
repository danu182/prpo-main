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
        Schema::table('bill_requests', function (Blueprint $table) {
            // Ubah dari integer ke string agar bisa menyimpan 'months', 'weeks', dll
            $table->string('recurring_period')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            $table->dropColumn('recurring_period');
        });
    }
};
