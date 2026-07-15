<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            // 1. Hapus kunci 'unique' lama pada document_type agar kita bisa buat matriks ganda
            $table->dropUnique(['document_type']);

            // 2. Tambahkan kolom departemen (KOSONG = Berlaku Umum / Default)
            $table->unsignedBigInteger('department_id')->nullable()->after('document_type');

            // 3. Buat kunci 'unique' GABUNGAN (1 Dokumen + 1 Departemen hanya boleh punya 1 Matriks)
            $table->unique(['document_type', 'department_id'], 'workflow_doc_dept_unique');
        });
    }

    public function down()
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->dropUnique('workflow_doc_dept_unique');
            $table->dropColumn('department_id');
            $table->unique('document_type');
        });
    }
};
