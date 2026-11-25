<?php

namespace App\Services;

use App\Models\SuratKeluar;
use App\Support\Identitas;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Isi surat terbit dibekukan agar perubahan template, kop, atau tanda tangan setelahnya tidak
 * mengubah dokumen yang dipakai untuk membuktikan keaslian.
 */
class ArsipSurat
{
    /** Nomor resmi masih boleh dikoreksi setelah terbit, jadi disimpan sebagai penanda. */
    private const PENANDA_NOMOR = '__NOMOR_SURAT_ARSIP__';

    /**
     * Arsip dibuat bila belum ada.
     *
     * @return array{isi: string, lampiran: string, penandatangan: ?array, ttd: ?string, kop: ?string, identitas: array, logo: ?string}
     */
    public static function ambil(SuratKeluar $surat): array
    {
        $disk = Storage::disk('local');
        $path = 'arsip-surat/'.$surat->uuid_validasi.'.json';

        if (! $disk->exists($path) && ! $disk->put($path, json_encode(self::susun($surat), JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Arsip surat tidak dapat disimpan. Periksa izin tulis folder storage.');
        }

        $arsip = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        foreach (['isi', 'lampiran'] as $bagian) {
            $arsip[$bagian] = str_replace(self::PENANDA_NOMOR, e($surat->nomor_surat_resmi), $arsip[$bagian]);
        }

        // Arsip yang dibuat sebelum identitas bisa diatur selalu memakai nilai config, bukan Pengaturan.
        $arsip['identitas'] ??= Arr::only(config('prodi'), Identitas::PADA_SURAT);
        $arsip['logo'] ??= LogoService::bawaanDataUri();

        return $arsip;
    }

    private static function susun(SuratKeluar $surat): array
    {
        $contoh = clone $surat;
        $contoh->nomor_surat_resmi = self::PENANDA_NOMOR;
        $penandatangan = SuratRenderer::penandatangan($surat);

        $ttd = null;
        if ($surat->metodeTtd($penandatangan) === SuratKeluar::TTD_DIGITAL && $penandatangan?->punyaTandaTangan()) {
            $ttd = 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($penandatangan->signature_path));
        }

        // String kosong, bukan null, agar kop yang diunggah belakangan tidak muncul pada surat lama.
        $kop = KopSuratService::dataUri() ?? '';

        return SuratRenderer::bagian($contoh) + [
            'penandatangan' => $penandatangan?->only(['name', 'jabatan', 'nidn']),
            'ttd' => $ttd,
            'kop' => $kop,
            'identitas' => Arr::only(Identitas::semua(), Identitas::PADA_SURAT),
            // Logo hanya tercetak pada kop teks.
            'logo' => $kop === '' ? (LogoService::dataUri() ?? '') : '',
        ];
    }
}
