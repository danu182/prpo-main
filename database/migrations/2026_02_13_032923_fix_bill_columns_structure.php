<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            // 1. BUAT BARU: recurring_interval (karena error bilang kolom ini tidak ada)
            // Cek dulu biar aman, kalau belum ada baru dibuat
            if (!Schema::hasColumn('bill_requests', 'recurring_interval')) {
                $table->integer('recurring_interval')->nullable()->after('recurring_period');
            }

            // 2. UBAH KOLOM YANG ADA: recurring_period (dari Int ke String & Nullable)
            // Pastikan paket doctrine/dbal terinstall untuk mengubah tipe data
            $table->string('recurring_period')->nullable()->change();

            // 3. UBAH KOLOM LAINNYA (Make Nullable)
            $table->date('next_generation_date')->nullable()->change();
            $table->string('type')->nullable()->change();
            $table->string('category')->nullable()->change();
            $table->string('title')->nullable()->change();
            $table->date('due_date')->nullable()->change();

            // 4. BERI NILAI DEFAULT (Untuk angka)
            $table->decimal('amount', 15, 2)->default(0)->change();
            $table->decimal('total_discount', 15, 2)->default(0)->change();
            $table->integer('current_approval_level')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            // Rollback logic (Opsional, sesuaikan jika perlu)
            $table->dropColumn('recurring_interval');
        });
    }
};
