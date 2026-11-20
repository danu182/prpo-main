<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buat kolom fixed_asset_id di header boleh kosong (nullable)
        if (Schema::hasColumn('fixed_asset_transactions', 'fixed_asset_id')) {
            Schema::table('fixed_asset_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('fixed_asset_id')->nullable()->change();
            });
        }

        // Buat kolom warehouse_id di header jika belum ada
        if (!Schema::hasColumn('fixed_asset_transactions', 'warehouse_id')) {
            Schema::table('fixed_asset_transactions', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('to_user_id')->constrained('warehouses', 'id', 'fat_warehouse_fk')->nullOnDelete();
            });
        }

        // Bersihkan jika tabel sempat terbuat separuh saat error sebelumnya
        Schema::dropIfExists('fixed_asset_transaction_details');

        // Buat tabel detail transaksi aset dengan nama index foreign key pendek (< 64 karakter)
        Schema::create('fixed_asset_transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_transaction_id')
                ->constrained('fixed_asset_transactions', 'id', 'fat_det_trx_fk')
                ->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')
                ->constrained('fixed_assets', 'id', 'fat_det_asset_fk')
                ->cascadeOnDelete();
            $table->foreignId('previous_warehouse_id')
                ->nullable()
                ->constrained('warehouses', 'id', 'fat_det_prev_wh_fk')
                ->nullOnDelete();
            $table->foreignId('previous_user_id')
                ->nullable()
                ->constrained('users', 'id', 'fat_det_prev_usr_fk')
                ->nullOnDelete();
            $table->string('item_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_transaction_details');
    }
};