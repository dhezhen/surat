<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_masuks', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_agenda')->unique();
            $table->string('nomor_surat_asal');
            $table->date('tanggal_surat');
            $table->date('tanggal_diterima');
            $table->string('pengirim');
            $table->string('perihal');
            $table->string('kategori');
            $table->text('keterangan')->nullable();
            $table->string('file_scan_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_masuks');
    }
};
