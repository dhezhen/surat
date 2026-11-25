<?php

namespace App\Services;

use App\Models\Pengaturan;
use GdImage;
use InvalidArgumentException;

/** Logo untuk menu, halaman masuk, ikon tab, dan kop teks. */
class LogoService extends GambarPengaturan
{
    public const SISI_MIN = 128;
    private const SISI_MAKS = 400;

    protected const KUNCI = 'app.logo';
    protected const BAWAAN = 'assets/logo-uniku.png';
    protected const NAMA = 'Logo';

    public static function url(): string
    {
        // Nama berkas unggahan selalu baru, jadi dipakai sebagai penanda versi untuk cache browser.
        return self::pakaiUnggahan()
            ? route('logo', ['v' => basename(Pengaturan::ambil(self::KUNCI), '.png')])
            : asset(self::BAWAAN);
    }

    protected function olah(GdImage $asal, int $lebar, int $tinggi): string
    {
        if (min($lebar, $tinggi) < self::SISI_MIN) {
            throw new InvalidArgumentException('Logo minimal '.self::SISI_MIN.' × '.self::SISI_MIN.' piksel.');
        }
        if (max($lebar, $tinggi) > min($lebar, $tinggi) * 1.5) {
            throw new InvalidArgumentException('Logo harus mendekati persegi. Potong ruang kosong di sekelilingnya.');
        }

        $skala = min(1, self::SISI_MAKS / max($lebar, $tinggi));
        [$w, $h] = [(int) round($lebar * $skala), (int) round($tinggi * $skala)];
        $hasil = imagecreatetruecolor($w, $h);
        imagealphablending($hasil, false);
        imagesavealpha($hasil, true);
        imagefill($hasil, 0, 0, imagecolorallocatealpha($hasil, 255, 255, 255, 127));
        imagecopyresampled($hasil, $asal, 0, 0, 0, 0, $w, $h, $lebar, $tinggi);

        return self::keString($hasil);
    }
}
