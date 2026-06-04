<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->onDelete('cascade');
            $table->integer('step_order')->comment('Urutan: 1, 2, 3');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade')->comment('Dari Spatie Roles');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
    }
};
