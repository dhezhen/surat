<?php

namespace App\Support;

use App\Models\Pengaturan;

/**
 * Identitas prodi, penomoran, dan teks aplikasi. Nilai dari halaman Pengaturan didahulukan;
 * yang belum pernah diisi memakai config/prodi.php.
 */
class Identitas
{
    public const KUNCI = [
        'nama_aplikasi', 'nama_unit', 'nama_universitas', 'nama_fakultas', 'nama_prodi', 'alamat', 'kota',
        'label_id_penandatangan', 'kode_fakultas', 'kode_prodi', 'format_nomor', 'nomor_pad', 'kategori_surat_masuk',
    ];

    /** Isian yang tercetak pada surat, sehingga ikut dibekukan saat surat terbit. */
    public const PADA_SURAT = ['nama_universitas', 'nama_fakultas', 'nama_prodi', 'alamat', 'kota', 'label_id_penandatangan'];

    /** Disimpan sebagai teks, satu butir per baris. */
    private const DAFTAR = ['alamat', 'kategori_surat_masuk'];

    private const MEMO = 'identitas';

    public static function semua(): array
    {
        // Disimpan di container agar satu permintaan cukup sekali membaca database.
        if (! app()->bound(self::MEMO)) {
            $tersimpan = Pengaturan::where('kunci', 'like', 'prodi.%')->pluck('nilai', 'kunci');
            $hasil = [];
            foreach (self::KUNCI as $kunci) {
                $nilai = $tersimpan['prodi.'.$kunci] ?? null;
                $hasil[$kunci] = match (true) {
                    $nilai === null || $nilai === '' => config('prodi.'.$kunci),
                    in_array($kunci, self::DAFTAR, true) => self::baris($nilai),
                    default => $nilai,
                };
            }
            app()->instance(self::MEMO, $hasil);
        }

        return app(self::MEMO);
    }

    public static function ambil(string $kunci): mixed
    {
        return self::semua()[$kunci];
    }

    /** @param array<string, string|int|null> $nilai */
    public static function simpan(array $nilai): void
    {
        foreach ($nilai as $kunci => $isi) {
            Pengaturan::simpan('prodi.'.$kunci, $isi === null ? null : (string) $isi);
        }
        app()->forgetInstance(self::MEMO);
    }

    /** @return list<string> */
    public static function baris(?string $teks): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $teks)), fn ($b) => $b !== ''));
    }
}
