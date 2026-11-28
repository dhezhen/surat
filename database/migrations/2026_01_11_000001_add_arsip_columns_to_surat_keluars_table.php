<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            // "manual": surat yang terbit di luar sistem (mis. buku agenda) dan dicatat Super Admin.
            $table->string('sumber', 10)->default('sistem')->after('status')->index();
            $table->string('berkas_path')->nullable()->after('sumber');
            $table->text('keterangan_arsip')->nullable()->after('berkas_path');
            $table->string('alasan_hapus')->nullable()->after('keterangan_arsip');
            $table->foreignId('dihapus_oleh_id')->nullable()->after('alasan_hapus')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dihapus_oleh_id');
            $table->dropColumn(['sumber', 'berkas_path', 'keterangan_arsip', 'alasan_hapus']);
        });
    }
};
