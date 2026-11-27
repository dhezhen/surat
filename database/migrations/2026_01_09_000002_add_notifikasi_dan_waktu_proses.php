<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waktu diajukan dan disetujui dipakai laporan untuk menghitung lama persetujuan.
 * Surat lama tidak punya data ini sehingga tidak ikut dihitung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->timestamp('diajukan_at')->nullable()->after('catatan_revisi');
            $table->timestamp('disetujui_at')->nullable()->after('diajukan_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notif_email')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropColumn(['diajukan_at', 'disetujui_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notif_email');
        });
    }
};
