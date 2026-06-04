<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. BUAT TABEL STATUSES (MASTER)
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index(); // Tipe: 'PR', 'PO', 'GR', 'INVOICE'
            $table->string('name');              // Display: 'Menunggu Approval'
            $table->string('slug');              // Code: 'pending_approval'
            $table->string('color')->default('secondary'); // Bootstrap class: primary, warning, etc
            $table->integer('sequence')->default(0);       // Urutan sorting
            $table->timestamps();

            // Mencegah duplikasi slug di tipe yang sama
            $table->unique(['type', 'slug']);
        });

        // 2. UPDATE TABEL PURCHASE_REQUESTS (PR)
        Schema::table('purchase_requests', function (Blueprint $table) {
            // Hapus kolom status lama (string) jika ada
            if (Schema::hasColumn('purchase_requests', 'status')) {
                $table->dropColumn('status');
            }

            // Tambahkan kolom status_id baru (Foreign Key)
            $table->foreignId('status_id')
                  ->nullable() // Boleh null dulu untuk data lama
                  ->after('id') // Posisi kolom (opsional)
                  ->constrained('statuses') // Relasi ke tabel 'statuses'
                  ->onDelete('set null');   // Jika status master dihapus, data PR jadi null (aman)
        });

        // 3. UPDATE TABEL PURCHASE_ORDERS (PO)
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Hapus kolom status lama (string) jika ada
            if (Schema::hasColumn('purchase_orders', 'status')) {
                $table->dropColumn('status');
            }

            // Jika sebelumnya Anda sempat membuat kolom 'purchase_order_status_id', hapus juga
            if (Schema::hasColumn('purchase_orders', 'purchase_order_status_id')) {
                $table->dropForeign(['purchase_order_status_id']); // Hapus FK lama
                $table->dropColumn('purchase_order_status_id');    // Hapus kolom lama
            }

            // Tambahkan kolom status_id baru (Foreign Key)
            $table->foreignId('status_id')
                  ->nullable()
                  ->after('bill_to_company_id') // Sesuaikan posisi
                  ->constrained('statuses')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke kondisi semula (Rollback)

        // 1. Rollback PO
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
            $table->string('status')->nullable(); // Kembalikan kolom string
        });

        // 2. Rollback PR
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
            $table->string('status')->nullable(); // Kembalikan kolom string
        });

        // 3. Hapus tabel statuses
        Schema::dropIfExists('statuses');
    }
};
