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
        Schema::table('purchase_request_item_vendors', function (Blueprint $table) {
            // 1. Kolom Mata Uang (Relasi ke tabel currencies)
            if (!Schema::hasColumn('purchase_request_item_vendors', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('vendor_id');
            }

            // 2. Kolom Lampiran (Untuk simpan PATH file di Storage, format JSON untuk Multi-File)
            if (!Schema::hasColumn('purchase_request_item_vendors', 'attachment')) {
                $table->text('attachment')->nullable()->after('quoted_price');
            }

            // 3. Kolom Link Referensi (Cth: Link Tokopedia/Shopee)
            if (!Schema::hasColumn('purchase_request_item_vendors', 'reference_link')) {
                $table->text('reference_link')->nullable()->after('attachment');
            }

            // 4. Kolom Catatan Khusus Vendor
            if (!Schema::hasColumn('purchase_request_item_vendors', 'notes')) {
                $table->text('notes')->nullable()->after('reference_link');
            }

            // Opsional: Hapus kolom currency lama (string) jika Komandan ingin migrasi total ke currency_id
            // if (Schema::hasColumn('purchase_request_item_vendors', 'currency')) {
            //     $table->dropColumn('currency');
            // }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_request_item_vendors', function (Blueprint $table) {
            $table->dropColumn(['currency_id', 'attachment', 'reference_link', 'notes']);
        });
    }
};
