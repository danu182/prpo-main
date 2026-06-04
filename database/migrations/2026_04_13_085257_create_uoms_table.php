<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // Contoh: PCS, BOX, LSN
            $table->string('name'); // Contoh: Pieces, Kardus, Lusin
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('uoms'); }
};
