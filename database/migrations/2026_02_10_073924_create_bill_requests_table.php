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
        Schema::create('bill_requests', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique(); // Contoh: BILL/2026/02/001
            $table->foreignId('user_id')->constrained('users'); // Requester
            $table->foreignId('company_id')->constrained('companies');

            // Kategori
            $table->string('type'); // 'ROUTINE' (Listrik/Net) atau 'NON_ROUTINE' (Service)
            $table->string('category'); // Listrik, Internet, Sewa, Service AC, Kendaraan, dll

            // Detail Tagihan
            $table->string('vendor_name'); // Bisa relasi ke table vendors atau string bebas
            $table->text('description')->nullable();
            $table->date('invoice_date');
            $table->date('due_date'); // Jatuh tempo
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');


            // Fitur Recurring (Pengingat/Auto Draft)
            $table->boolean('is_recurring')->default(false);
            $table->integer('recurring_period')->nullable(); // Dalam Bulan (1, 6, 12)
            $table->date('next_generation_date')->nullable(); // Kapan draft berikutnya dibuat

            // Status Approval (Sama seperti PR)
            $table->string('status')->default('DRAFT'); // DRAFT, PENDING, APPROVED_MANAGER, APPROVED, REJECTED, PAID
            $table->text('rejection_reason')->nullable();
            $table->integer('current_approval_level')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_requests');
    }
};
