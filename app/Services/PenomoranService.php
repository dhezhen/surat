<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Support\Identitas;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PenomoranService
{
    private const ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /**
     * Nomor yang akan dipakai jika surat disetujui sekarang. Hanya perkiraan untuk ditampilkan saat
     * surat disusun: tidak dikunci, dan berubah bila surat lain terbit lebih dulu.
     */
    public static function perkiraan(): string
    {
        $sekarang = now();

        return self::susun(max(self::urutTerpakai($sekarang->year) + 1, self::nomorAwal($sekarang->year)), $sekarang);
    }

    /** Nomor urut tertinggi pada tahun itu. Surat terhapus tetap dihitung agar nomornya tidak dipakai ulang. */
    public static function urutTerpakai(int $tahun): int
    {
        return (int) SuratKeluar::withTrashed()->where('tahun_terbit', $tahun)->max('nomor_urut');
    }

    /** Batas bawah nomor urut, mis. untuk melanjutkan buku agenda manual. Hanya berlaku pada tahun diaturnya. */
    public static function nomorAwal(int $tahun): int
    {
        [$untuk, $awal] = array_pad(explode(':', (string) Pengaturan::ambil('nomor.awal')), 2, 0);

        return (int) $untuk === $tahun ? max(1, (int) $awal) : 1;
    }

    public static function aturNomorAwal(int $tahun, ?int $awal): void
    {
        Pengaturan::simpan('nomor.awal', $awal === null ? null : "{$tahun}:{$awal}");
    }

    private static function susun(int $urut, \Illuminate\Support\Carbon $waktu): string
    {
        $identitas = Identitas::semua();

        return strtr($identitas['format_nomor'], [
            '{urut}' => str_pad((string) $urut, (int) $identitas['nomor_pad'], '0', STR_PAD_LEFT),
            '{fakultas}' => $identitas['kode_fakultas'],
            '{prodi}' => $identitas['kode_prodi'],
            '{bulan}' => self::ROMAWI[(int) $waktu->month],
            '{tahun}' => $waktu->year,
        ]);
    }

    /**
     * Menerbitkan surat: memberi nomor urut berikutnya pada tahun berjalan dan mengunci dokumen.
     * Persetujuan diantrekan dengan kunci cache karena lockForUpdate saja berakhir deadlock di MySQL
     * saat beberapa surat disetujui bersamaan.
     */
    public function terbitkan(SuratKeluar $surat, User $penyetuju, ?string $metodeTtd = null, ?bool $tampilPdfPublik = null): SuratKeluar
    {
        return Cache::lock('nomor-surat-keluar', 10)->block(10, fn () => DB::transaction(function () use ($surat, $penyetuju, $metodeTtd, $tampilPdfPublik) {
            $surat = SuratKeluar::with('jenisSurat')->lockForUpdate()->findOrFail($surat->id);

            if ($surat->status !== SuratKeluar::MENUNGGU) {
                throw new \DomainException('Surat tidak berstatus menunggu persetujuan.');
            }

            $metode = $metodeTtd ?? $surat->metodeTtd($penyetuju);
            if ($metode === SuratKeluar::TTD_DIGITAL && ! $penyetuju->punyaTandaTangan()) {
                throw new \DomainException('TTD digital memerlukan gambar tanda tangan. Unggah di menu Profil, atau pilih metode tanda tangan lain.');
            }

            $sekarang = now();
            $tahun = (int) $sekarang->year;

            $terakhir = SuratKeluar::withTrashed()->where('tahun_terbit', $tahun)
                ->lockForUpdate()
                ->max('nomor_urut');
            $urut = max(((int) $terakhir) + 1, self::nomorAwal($tahun));

            $surat->forceFill([
                'nomor_urut' => $urut,
                'nomor_surat_resmi' => self::susun($urut, $sekarang),
                'tahun_terbit' => $tahun,
                'tanggal_surat' => $sekarang->toDateString(),
                'penyetuju_id' => $penyetuju->id,
                'status' => SuratKeluar::TERBIT,
                'metode_ttd' => $metode,
                'tampil_pdf_publik' => $tampilPdfPublik ?? $surat->tampilPdfPublik(),
                'catatan_revisi' => null,
            ])->save();

            return $surat;
        }));
    }
}
