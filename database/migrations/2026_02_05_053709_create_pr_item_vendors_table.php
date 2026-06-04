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
        Schema::create('pr_item_vendors', function (Blueprint $table) {
           $table->id();
            $table->foreignId('pr_item_id')->constrained('purchase_request_items')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->decimal('quoted_price', 15, 2)->default(0);
            $table->text('notes')->nullable(); // Misal: "Termasuk ongkir"
            $table->boolean('is_selected')->default(false); // Opsional: jika nanti user memilih vendor ini
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_item_vendors');
    }
};
