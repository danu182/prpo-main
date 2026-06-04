<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================
        // 1. UPDATE TABEL `item_import_details`
        // =========================================================
        if (!Schema::hasColumn('item_import_details', 'item_type_code')) {
            Schema::table('item_import_details', function (Blueprint $table) {
                // Di tabel ini uom_code pasti ada, jadi aman pakai after
                $table->string('item_type_code', 50)->nullable()->after('uom_code');
            });
        }

        if (Schema::hasColumn('item_import_details', 'is_stockable')) {
            DB::table('item_import_details')->where('is_stockable', 1)->update(['item_type_code' => 'STK']);
            DB::table('item_import_details')->where('is_stockable', 0)->update(['item_type_code' => 'JSA']);

            Schema::table('item_import_details', function (Blueprint $table) {
                $table->dropColumn('is_stockable');
            });
        }

        // =========================================================
        // 2. UPDATE TABEL `items` (MASTER BARANG)
        // =========================================================
        if (!Schema::hasColumn('items', 'item_type_code')) {
            Schema::table('items', function (Blueprint $table) {
                // 🔥 HAPUS ->after() AGAR AMAN DITARUH DI PALING BELAKANG 🔥
                $table->string('item_type_code', 50)->nullable();
            });
        }

        if (Schema::hasColumn('items', 'is_stockable')) {
            DB::table('items')->where('is_stockable', 1)->update(['item_type_code' => 'STK']);
            DB::table('items')->where('is_stockable', 0)->update(['item_type_code' => 'JSA']);

            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('is_stockable');
            });
        }
    }

    public function down(): void
    {
        // Rollback untuk tabel items
        if (!Schema::hasColumn('items', 'is_stockable')) {
            Schema::table('items', function (Blueprint $table) {
                $table->boolean('is_stockable')->default(true);
            });
            DB::table('items')->where('item_type_code', 'STK')->update(['is_stockable' => 1]);
            DB::table('items')->where('item_type_code', '!=', 'STK')->update(['is_stockable' => 0]);
        }
        if (Schema::hasColumn('items', 'item_type_code')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('item_type_code');
            });
        }

        // Rollback untuk tabel item_import_details
        if (!Schema::hasColumn('item_import_details', 'is_stockable')) {
            Schema::table('item_import_details', function (Blueprint $table) {
                $table->boolean('is_stockable')->default(true);
            });
            DB::table('item_import_details')->where('item_type_code', 'STK')->update(['is_stockable' => 1]);
            DB::table('item_import_details')->where('item_type_code', '!=', 'STK')->update(['is_stockable' => 0]);
        }
        if (Schema::hasColumn('item_import_details', 'item_type_code')) {
            Schema::table('item_import_details', function (Blueprint $table) {
                $table->dropColumn('item_type_code');
            });
        }
    }
};
