<?php

use App\Models\SuratKeluar;
use App\Services\ArsipSurat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('surat:arsipkan', function () {
    $surats = SuratKeluar::withTrashed()->with(['jenisSurat', 'penyetuju'])->where('status', SuratKeluar::TERBIT)->get();
    $surats->each(fn ($surat) => ArsipSurat::ambil($surat));
    $this->info($surats->count().' surat terbit sudah memiliki arsip.');
})->purpose('Membekukan isi surat terbit yang belum punya arsip');
