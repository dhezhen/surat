<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peran tambahan hanya memberi nama baru; kewenangannya mengikuti salah satu peran bawaan (hak_akses).
        Schema::create('perans', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50)->unique();
            $table->string('hak_akses');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('peran_id')->nullable()->after('role')->constrained('perans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('peran_id');
        });
        Schema::dropIfExists('perans');
    }
};
