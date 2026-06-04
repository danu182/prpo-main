<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Kode Vendor Unik
            $table->string('code', 50)->unique()->after('id')->nullable();

            // Data Contact Person (PIC)
            $table->string('pic_name')->nullable()->after('address');
            $table->string('pic_phone', 50)->nullable()->after('pic_name');

            // Data Keuangan (Finance)
            $table->string('bank_name', 100)->nullable()->after('pic_phone')->comment('Contoh: BCA, Mandiri');
            $table->string('bank_account_number', 100)->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account_number')->comment('Nama Pemilik Rekening');

            // Term Of Payment (TOP)
            $table->integer('payment_terms_days')->default(0)->after('bank_account_name')->comment('0 = Cash, 30 = Net 30 Hari');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'pic_name',
                'pic_phone',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'payment_terms_days'
            ]);
        });
    }
};
