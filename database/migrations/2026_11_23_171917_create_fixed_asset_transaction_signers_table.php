<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fixed_asset_transaction_signers');

        Schema::create('fixed_asset_transaction_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_transaction_id')
                  ->constrained('fixed_asset_transactions', 'id', 'fk_fa_trx_signers_trx_id')
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained('users', 'id', 'fk_fa_trx_signers_user_id')
                  ->onDelete('cascade');
            $table->string('role_label', 100)->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('signature_mode', 20)->default('digital');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_transaction_signers');
    }
};
