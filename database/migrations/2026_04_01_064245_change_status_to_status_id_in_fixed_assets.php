<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // 1. Buat kolom status_id (Boleh kosong sementara untuk proses transisi)
            $table->foreignId('status_id')->nullable()->after('status')->constrained('statuses')->nullOnDelete();
        });

        // 2. TRANSFER DATA LAMA (Opsional tapi direkomendasikan agar data lama tidak hilang)
        // Pastikan Anda sudah membuat status AST di tabel statuses (Langkah 1) sebelum menjalankan ini!
        $statusAvailable = DB::table('statuses')->where('type', 'AST')->where('slug', 'available')->value('id');
        if($statusAvailable) {
             DB::table('fixed_assets')->where('status', 'Available')->update(['status_id' => $statusAvailable]);
             DB::table('fixed_assets')->whereNull('status_id')->update(['status_id' => $statusAvailable]); // Default untuk yang kosong
        }

        Schema::table('fixed_assets', function (Blueprint $table) {
             // 3. Hapus kolom status ENUM yang lama
             $table->dropColumn('status');
        });
    }

    public function down()
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
            // Kembalikan ke ENUM jika di-rollback
            $table->enum('status', ['Available', 'In Use', 'Maintenance', 'Disposed', 'Returned'])->default('Available');
        });
    }
};
