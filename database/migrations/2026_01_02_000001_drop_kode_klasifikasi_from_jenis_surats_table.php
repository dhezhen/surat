<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_surats', function (Blueprint $table) {
            $table->dropUnique(['kode_klasifikasi']);
        });

        Schema::table('jenis_surats', function (Blueprint $table) {
            $table->dropColumn('kode_klasifikasi');
            $table->unique('nama_surat');
        });
    }

    public function down(): void
    {
        Schema::table('jenis_surats', function (Blueprint $table) {
            $table->dropUnique(['nama_surat']);
            $table->string('kode_klasifikasi')->nullable()->unique();
        });
    }
};
