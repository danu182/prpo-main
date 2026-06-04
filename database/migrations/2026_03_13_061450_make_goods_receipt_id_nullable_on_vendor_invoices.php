<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            // Mengubah kolom menjadi Boleh Kosong (Nullable)
            $table->unsignedBigInteger('goods_receipt_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            // Mengembalikan seperti semula jika di-rollback
            $table->unsignedBigInteger('goods_receipt_id')->nullable(false)->change();
        });
    }
};
