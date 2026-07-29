<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_photos', function (Blueprint $table) {
            $table->id();
            // Relasi ke tabel fixed_assets (jika aset dihapus, foto di DB ikut terhapus)
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->onDelete('cascade');
            $table->string('file_path'); // Rute file foto
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_photos');
    }
};
