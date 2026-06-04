<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom qty_returned di tabel goods_issue_items (untuk melacak sisa yang belum diretur)
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->decimal('qty_returned', 15, 2)->default(0)->after('qty_issued');
        });

        // 2. Buat tabel Header Retur
        Schema::create('goods_issue_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_issue_id')->constrained('goods_issues')->cascadeOnDelete();
            $table->string('return_number')->unique();
            $table->date('return_date');
            $table->string('returned_by_name')->comment('Nama orang yang mengembalikan barang');
            $table->foreignId('received_by')->nullable()->constrained('users')->comment('Petugas gudang penerima');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Buat tabel Detail Item Retur
        Schema::create('goods_issue_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_issue_return_id')->constrained('goods_issue_returns')->cascadeOnDelete();
            $table->foreignId('goods_issue_item_id')->constrained('goods_issue_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('qty_returned', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issue_return_items');
        Schema::dropIfExists('goods_issue_returns');
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->dropColumn('qty_returned');
        });
    }
};
