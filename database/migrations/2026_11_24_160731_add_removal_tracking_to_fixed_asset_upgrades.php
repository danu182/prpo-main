<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_asset_upgrades', function (Blueprint $table) {
            if (!Schema::hasColumn('fixed_asset_upgrades', 'status')) {
                // ACTIVE = Terpasang, REMOVED_GOOD = Dicopot (Masuk Stok Bagus), REMOVED_DEFECTIVE = Dicopot (Rusak/RMA)
                $table->string('status', 30)->default('ACTIVE')->after('is_capitalized');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'removed_at')) {
                $table->date('removed_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'removal_number')) {
                $table->string('removal_number', 50)->nullable()->after('removed_at');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'removal_reason')) {
                $table->text('removal_reason')->nullable()->after('removal_number');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'removed_by')) {
                $table->foreignId('removed_by')->nullable()->after('removal_reason')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fixed_asset_upgrades', function (Blueprint $table) {
            $cols = ['status', 'removed_at', 'removal_number', 'removal_reason', 'removed_by'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('fixed_asset_upgrades', $c));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};