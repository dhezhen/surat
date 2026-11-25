<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->string('metode_ttd', 20)->nullable()->after('status');
        });

        // Surat yang sudah terbit dicetak dengan QR Code dan gambar tanda tangan; tampilannya dipertahankan.
        DB::table('surat_keluars')->where('status', 'terbit')->update(['metode_ttd' => 'digital']);
    }

    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropColumn('metode_ttd');
        });
    }
};
