<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_issues', function (Blueprint $table) {
            $table->id();
            $table->string('gi_number')->unique(); // Nomor Dokumen Pengeluaran (Contoh: GI/2026/03/0001)
            $table->date('issue_date'); // Tanggal dikeluarkan
            $table->string('requester_name'); // Nama Karyawan yang meminta/mengambil
            $table->string('department')->nullable(); // Departemen yang meminta (IT, HRD, dll)
            $table->text('notes')->nullable(); // Catatan tambahan

            // Staf Gudang yang memproses pengeluaran
            $table->foreignId('issued_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issues');
    }
};
