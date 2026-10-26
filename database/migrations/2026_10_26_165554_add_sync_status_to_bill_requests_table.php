<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            $table->string('sync_status')->default('PENDING')->after('status_id');
            $table->integer('sync_retries')->default(0)->after('sync_status');
            $table->text('last_sync_error')->nullable()->after('sync_retries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_requests', function (Blueprint $table) {
            //
        });
    }
};
