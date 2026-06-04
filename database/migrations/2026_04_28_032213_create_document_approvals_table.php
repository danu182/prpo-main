<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            // MorphTo agar 1 tabel bisa dipakai PR, PO, atau dokumen lain ke depannya
            $table->morphs('document');
            $table->integer('step_order');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('status')->default('PENDING')->comment('PENDING, APPROVED, REJECTED');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
    }
};
