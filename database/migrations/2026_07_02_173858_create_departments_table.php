<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. MEMBUAT TABEL UTAMA DEPARTEMEN
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 🔥 2. SUNTIK KOLOM KE TABEL USERS (INI SOLUSI ERRORNYA) 🔥
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'department_id')) {
                    // Karena di user Anda mungkin belum ada company_id, kita letakkan setelah email saja agar aman
                    $table->foreignId('department_id')->nullable()->after('email')->constrained('departments')->onDelete('set null');
                }
            });
        }

        // 3. SUNTIK KOLOM KE TABEL FIXED_ASSETS
        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('fixed_assets', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
                }
            });
        }

        // 4. SUNTIK KOLOM KE TABEL KARANTINA IMPORT
        if (Schema::hasTable('fixed_asset_import_details')) {
            Schema::table('fixed_asset_import_details', function (Blueprint $table) {
                if (!Schema::hasColumn('fixed_asset_import_details', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }

        if (Schema::hasTable('fixed_assets') && Schema::hasColumn('fixed_assets', 'department_id')) {
            Schema::table('fixed_assets', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }

        if (Schema::hasTable('fixed_asset_import_details') && Schema::hasColumn('fixed_asset_import_details', 'department_id')) {
            Schema::table('fixed_asset_import_details', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }

        Schema::dropIfExists('departments');
    }
};
