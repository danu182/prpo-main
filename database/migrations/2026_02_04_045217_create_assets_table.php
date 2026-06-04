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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('company_id')->constrained('companies'); // Aset milik siapa
            $table->foreignId('gr_item_id')->nullable()->constrained('goods_receipt_items'); // Asal usul barang

            // Diisi oleh Finance
            $table->string('asset_tag')->nullable()->unique(); // Nomor Inventaris
            $table->decimal('acquisition_value', 15, 2)->nullable();

            // Detail Fisik
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('current_user_id')->nullable()->constrained('users'); // Siapa pemegang aset

            $table->string('status')->default('PENDING_TAGGING'); // PENDING_TAGGING, ACTIVE, BROKEN, DISPOSED, MUTATED

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
