<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();

            $table->string('pr_number')->unique(); // PR/HO/2024/001
            $table->foreignId('user_id')->constrained('users'); // Pembuat PR
            $table->foreignId('company_id')->constrained('companies'); // PR untuk perusahaan mana
            $table->date('request_date');
            $table->text('description')->nullable();

            // Status Flow
            // Status: DRAFT, PENDING_APPROVAL, APPROVED, REJECTED, PROCESSING, COMPLETED
            $table->string('status')->default('DRAFT');
            $table->integer('current_approval_level')->default(0); // Posisi approval sekarang

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
