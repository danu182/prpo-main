<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table) {
            // Tambahkan kolom batas minimal nominal (Default 0 = Selalu ikut ACC)
            $table->decimal('min_amount', 15, 2)->default(0)->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table) {
            $table->dropColumn('min_amount');
        });
    }
};
