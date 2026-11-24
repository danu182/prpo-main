<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_asset_upgrades', function (Blueprint $table) {
            if (!Schema::hasColumn('fixed_asset_upgrades', 'upgrade_number')) {
                $table->string('upgrade_number', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'item_id')) {
                $table->foreignId('item_id')
                      ->nullable()
                      ->after('fixed_asset_id')
                      ->constrained('items', 'id', 'fk_fa_upg_item_id')
                      ->nullOnDelete();
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'warehouse_id')) {
                $table->foreignId('warehouse_id')
                      ->nullable()
                      ->after('item_id')
                      ->constrained('warehouses', 'id', 'fk_fa_upg_wh_id')
                      ->nullOnDelete();
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')
                      ->nullable()
                      ->after('warehouse_id')
                      ->constrained('purchase_orders', 'id', 'fk_fa_upg_po_id')
                      ->nullOnDelete();
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'goods_receipt_id')) {
                $table->foreignId('goods_receipt_id')
                      ->nullable()
                      ->after('purchase_order_id')
                      ->constrained('goods_receipts', 'id', 'fk_fa_upg_gr_id')
                      ->nullOnDelete();
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'qty')) {
                $table->decimal('qty', 10, 2)->default(1)->after('component_name');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'unit_cost')) {
                $table->decimal('unit_cost', 15, 2)->default(0)->after('qty');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'accounting_treatment')) {
                // EXPENSE (tidak ubah nilai/umur), CAPITALIZE_VALUE (tambah nilai saja), CAPITALIZE_EXTEND (tambah nilai + ubah umur)
                $table->string('accounting_treatment', 30)->default('EXPENSE')->after('cost');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'is_capitalized')) {
                $table->boolean('is_capitalized')->default(false)->after('accounting_treatment');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'book_value_before')) {
                $table->decimal('book_value_before', 15, 2)->nullable()->after('is_capitalized');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'book_value_after')) {
                $table->decimal('book_value_after', 15, 2)->nullable()->after('book_value_before');
            }
            if (!Schema::hasColumn('fixed_asset_upgrades', 'new_remaining_months')) {
                $table->integer('new_remaining_months')->nullable()->after('book_value_after');
            }
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('fixed_assets', 'extended_useful_life_months')) {
                $table->integer('extended_useful_life_months')->nullable()->after('asset_category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            if (Schema::hasColumn('fixed_assets', 'extended_useful_life_months')) {
                $table->dropColumn('extended_useful_life_months');
            }
        });

        Schema::table('fixed_asset_upgrades', function (Blueprint $table) {
            $cols = [
                'upgrade_number', 'item_id', 'warehouse_id', 'purchase_order_id', 'goods_receipt_id',
                'qty', 'unit_cost', 'accounting_treatment', 'is_capitalized',
                'book_value_before', 'book_value_after', 'new_remaining_months'
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('fixed_asset_upgrades', $c));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
