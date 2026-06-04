<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan fitur "Lacak Pemegang" di Master Barang
        Schema::table('items', function (Blueprint $table) {
            // Cek agar tidak error jika kolom sudah ada
            if (!Schema::hasColumn('items', 'is_trackable')) {
                $table->boolean('is_trackable')->default(false)->after('current_stock')->comment('True jika barang ini butuh tracking pemegang (Minor Asset)');
            }
        });

        // 2. Tabel "Dompet" Tanggungan Inventaris Karyawan
        Schema::create('employee_inventories', function (Blueprint $table) {
            $table->id();
            // Kita pakai string agar sinkron dengan 'requester_name' di Goods Issue
            $table->string('employee_name')->index();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('qty', 15, 2)->default(0);
            $table->timestamps();
        });

        // 3. Tabel Riwayat Jejak Perpindahan Inventaris
        Schema::create('employee_inventory_histories', function (Blueprint $table) {
            $table->id();
            $table->string('employee_name')->index();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('type', 10)->comment('IN (Terima), OUT (Keluar/Retur/Oper)');
            $table->decimal('qty', 15, 2);
            $table->string('reference_number')->nullable()->comment('No GI / No Retur / No Mutasi');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_inventory_histories');
        Schema::dropIfExists('employee_inventories');
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'is_trackable')) {
                $table->dropColumn('is_trackable');
            }
        });
    }
};
