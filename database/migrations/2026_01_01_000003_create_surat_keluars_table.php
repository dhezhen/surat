<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_keluars', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_validasi')->unique();
            $table->foreignId('jenis_surat_id')->constrained('jenis_surats');
            $table->foreignId('pembuat_id')->constrained('users');
            $table->foreignId('penyetuju_id')->nullable()->constrained('users');
            $table->unsignedInteger('nomor_urut')->nullable();
            $table->string('nomor_surat_resmi')->nullable()->unique();
            $table->unsignedSmallInteger('tahun_terbit')->nullable()->index();
            $table->date('tanggal_surat');
            $table->string('tujuan_surat');
            $table->string('perihal');
            $table->json('data_dinamis');
            $table->string('status')->default('draft')->index();
            $table->text('catatan_revisi')->nullable();
            $table->timestamps();

            $table->unique(['tahun_terbit', 'nomor_urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keluars');
    }
};
