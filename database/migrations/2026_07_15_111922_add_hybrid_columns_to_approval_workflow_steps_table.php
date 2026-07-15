<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table) {
            // Cek apakah kolom target_department_id belum ada
            if (!Schema::hasColumn('approval_workflow_steps', 'target_department_id')) {
                $table->unsignedBigInteger('target_department_id')->nullable()->after('role_id');
            }

            // Cek apakah kolom min_amount belum ada
            if (!Schema::hasColumn('approval_workflow_steps', 'min_amount')) {
                $table->decimal('min_amount', 15, 2)->default(0)->after('target_department_id');
            }
        });
    }

    public function down()
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table) {
            if (Schema::hasColumn('approval_workflow_steps', 'target_department_id')) {
                $table->dropColumn('target_department_id');
            }

            if (Schema::hasColumn('approval_workflow_steps', 'min_amount')) {
                $table->dropColumn('min_amount');
            }
        });
    }
};
