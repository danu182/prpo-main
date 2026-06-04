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
        Schema::table('bill_payments', function (Blueprint $table) {
            // Kolom untuk mencatat PT mana yang membayar (bisa beda dengan PT di tagihan)
            $table->foreignId('paid_by_company_id')
                ->nullable()
                ->after('bill_request_id')
                ->constrained('companies');
        });
    }

    public function down()
    {
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropForeign(['paid_by_company_id']);
            $table->dropColumn('paid_by_company_id');
        });
    }

};
