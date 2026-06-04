<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_issue_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->decimal('qty_issued', 10, 2); // Jumlah barang yang dikeluarkan
            $table->text('notes')->nullable(); // Keterangan per item
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issue_items');
    }
};
