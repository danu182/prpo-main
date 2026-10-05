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
        Schema::table('document_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('target_department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_approvals', function (Blueprint $table) {
            //
        });
    }
};
