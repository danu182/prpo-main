<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            // 🔥 Cek dulu: Kalau kolom 'slug' BELUM ADA, baru buatkan!
            if (!Schema::hasColumn('items', 'slug')) {
                $table->string('slug')->unique()->after('code');
            }
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
