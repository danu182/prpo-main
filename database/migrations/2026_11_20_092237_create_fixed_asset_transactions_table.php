<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique(); // Contoh: BAST/2026/11/0001 atau BAPA/2026/11/0001
            $table->enum('transaction_type', ['HANDOVER', 'RETURN', 'STATUS_CHANGE']);
            $table->date('transaction_date');
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Schema::dropIfExists('fixed_asset_transaction_details');
        Schema::dropIfExists('fixed_asset_transactions');
    }
};
