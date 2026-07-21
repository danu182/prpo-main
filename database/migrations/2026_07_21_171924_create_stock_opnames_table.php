<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // =========================================================
        // 1. TABEL HEADER STOCK OPNAME
        // =========================================================
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 50)->unique(); // Cth: SO-JKT-2026-07-001
            $table->unsignedBigInteger('company_id'); // Untuk deteksi Workflow Matriks
            $table->unsignedBigInteger('warehouse_id'); // Gudang yang dikunci
            $table->unsignedBigInteger('status_id')->nullable(); // Menempel ke Workflow (Draft, Counting, Pending Approval, Approved)

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();

            // Total Keseluruhan (Valuasi Finansial)
            $table->decimal('total_system_value', 15, 2)->default(0);  // Total Nilai Rupiah versi Sistem
            $table->decimal('total_actual_value', 15, 2)->default(0);  // Total Nilai Rupiah versi Fisik
            $table->decimal('total_variance_value', 15, 2)->default(0); // Total Nilai Absolut Selisih (Trigger Workflow Matriks)

            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // =========================================================
        // 2. TABEL RINCIAN ITEM STOCK OPNAME
        // =========================================================
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_opname_id');
            $table->unsignedBigInteger('item_id');

            // Satuan Dasar Master Barang (Base UOM)
            $table->string('base_uom', 50)->default('PCS');

            // Valuasi QTY
            $table->decimal('system_qty', 15, 4)->default(0);   // Stok di komputer saat gudang dikunci
            $table->decimal('actual_qty', 15, 4)->default(0);   // Hasil hitung fisik (sudah dikonversi ke Base UOM)
            $table->decimal('variance_qty', 15, 4)->default(0); // actual_qty - system_qty

            // Valuasi Rupiah (Harga HPP / Moving Average saat Opname)
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('system_value', 15, 2)->default(0);   // system_qty * unit_price
            $table->decimal('actual_value', 15, 2)->default(0);   // actual_qty * unit_price
            $table->decimal('variance_value', 15, 2)->default(0); // variance_qty * unit_price

            // Traceability: Jika user input pakai DUS/PACK, kita rekam agar tidak bingung
            $table->decimal('input_qty', 15, 4)->nullable();
            $table->unsignedBigInteger('input_uom_id')->nullable();

            $table->text('notes')->nullable(); // Alasan selisih (Cth: "Barang rusak dimakan tikus")

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
