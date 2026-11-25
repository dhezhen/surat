<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Validasi menerima perihal hingga 500 karakter; MySQL menolak isian yang melebihi lebar kolom.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_masuks', function (Blueprint $table) {
            $table->string('perihal', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('surat_masuks', function (Blueprint $table) {
            $table->string('perihal')->change();
        });
    }
};
