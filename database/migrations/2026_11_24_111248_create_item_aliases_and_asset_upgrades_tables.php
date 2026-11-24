<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Kamus Nama Alternatif / Varian per Master Item
        if (!Schema::hasTable('item_aliases')) {
            Schema::create('item_aliases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')
                      ->constrained('items', 'id', 'fk_item_aliases_item_id')
                      ->onDelete('cascade');
                $table->string('alias_name', 255);
                $table->string('source', 30)->default('MANUAL'); // PO, ASSET_MANUAL, ASSET_IMPORT
                $table->timestamps();

                $table->unique(['item_id', 'alias_name'], 'uq_item_alias_name');
            });
        }

        // 2. Tabel Rincian Komponen Upgrade Multi-Baris per Unit Aset
        if (!Schema::hasTable('fixed_asset_upgrades')) {
            Schema::create('fixed_asset_upgrades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fixed_asset_id')
                      ->constrained('fixed_assets', 'id', 'fk_fa_upgrades_asset_id')
                      ->onDelete('cascade');
                $table->date('upgrade_date')->nullable();
                $table->string('component_name', 255);
                $table->decimal('cost', 15, 2)->default(0);
                $table->foreignId('currency_id')
                      ->nullable()
                      ->constrained('currencies', 'id', 'fk_fa_upgrades_curr_id')
                      ->nullOnDelete();
                $table->string('vendor_or_executor', 255)->nullable(); // Contoh: Done by vendor / Internal IT
                $table->text('notes')->nullable();
                $table->foreignId('created_by')
                      ->nullable()
                      ->constrained('users', 'id', 'fk_fa_upgrades_user_id')
                      ->nullOnDelete();
                $table->timestamps();
            });
        }

        // 3. Tambah kolom item_alias_name di tabel fixed_assets
        Schema::table('fixed_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('fixed_assets', 'item_alias_name')) {
                $table->string('item_alias_name', 255)->nullable()->after('name');
            }
        });

        // 4. Tambah kolom nama_alternatif & upgrade_items di tabel karantina import
        if (Schema::hasTable('fixed_asset_import_details')) {
            Schema::table('fixed_asset_import_details', function (Blueprint $table) {
                if (!Schema::hasColumn('fixed_asset_import_details', 'nama_alternatif')) {
                    $table->string('nama_alternatif', 255)->nullable()->after('nama_spesifik_aset');
                }
                if (!Schema::hasColumn('fixed_asset_import_details', 'upgrade_items')) {
                    $table->text('upgrade_items')->nullable()->after('spesifikasi');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fixed_asset_import_details')) {
            Schema::table('fixed_asset_import_details', function (Blueprint $table) {
                $cols = array_filter(['nama_alternatif', 'upgrade_items'], fn($c) => Schema::hasColumn('fixed_asset_import_details', $c));
                if (!empty($cols)) $table->dropColumn($cols);
            });
        }

        Schema::table('fixed_assets', function (Blueprint $table) {
            if (Schema::hasColumn('fixed_assets', 'item_alias_name')) {
                $table->dropColumn('item_alias_name');
            }
        });

        Schema::dropIfExists('fixed_asset_upgrades');
        Schema::dropIfExists('item_aliases');
    }
};




