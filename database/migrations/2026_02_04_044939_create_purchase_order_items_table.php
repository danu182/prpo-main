<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            // --- RELASI ---
            // Jika PO induk dihapus, item ikut terhapus (Cascade)
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');

            $table->foreignId('item_id')->constrained('items'); // Master Barang

            // Link ke detail PR asal (untuk tracking: item ini berasal dari PR no berapa)
            // Pastikan tabel 'purchase_request_items' sudah ada. Jika belum, hapus baris ini.
            $table->foreignId('purchase_request_item_id')->nullable()->constrained('purchase_request_items')->onDelete('set null');

            $table->foreignId('tax_id')->nullable()->constrained('taxes'); // Master Pajak (PPN 11%, PPh, dll)

            // --- DETAIL BARANG ---
            $table->string('description')->nullable(); // Nama barang snapshot (jika user edit deskripsi manual)
            // $table->string('uom')->nullable(); // Unit of Measure Snapshot (Pcs, Box, Kg)
            $table->unsignedBigInteger('uom_id')->nullable();

            // --- QUANTITY ---
            $table->decimal('qty_ordered', 10, 2); // Jumlah dipesan
            $table->decimal('qty_received', 10, 2)->default(0); // Jumlah yang sudah diterima Gudang (GR)

            // --- HARGA & PAJAK ---
            $table->decimal('unit_price', 15, 2); // Harga satuan (Deal dengan Vendor)
            $table->decimal('subtotal', 15, 2); // qty * unit_price
            $table->decimal('tax_amount', 15, 2)->default(0); // Nominal pajak baris ini

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
