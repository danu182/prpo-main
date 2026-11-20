<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fixed_asset_transactions MODIFY COLUMN transaction_type VARCHAR(30) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fixed_asset_transactions MODIFY COLUMN transaction_type ENUM('HANDOVER','RETURN','STATUS_CHANGE') NOT NULL");
    }
};