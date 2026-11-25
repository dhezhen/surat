<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_surats', function (Blueprint $table) {
            $table->boolean('pdf_publik')->default(false);
        });
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->boolean('tampil_pdf_publik')->nullable();
        });

        // Surat lama bisa memuat data pribadi, jadi tertutup sampai dibuka satu per satu.
        DB::table('surat_keluars')->where('status', 'terbit')->update(['tampil_pdf_publik' => false]);
    }

    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropColumn('tampil_pdf_publik');
        });
        Schema::table('jenis_surats', function (Blueprint $table) {
            $table->dropColumn('pdf_publik');
        });
    }
};
