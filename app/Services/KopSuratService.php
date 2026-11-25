<?php

namespace App\Services;

use App\Models\Pengaturan;
use GdImage;
use InvalidArgumentException;

/** Kop berupa gambar utuh, atau teks yang disusun dari identitas prodi dan logo. */
class KopSuratService extends GambarPengaturan
{
    public const LEBAR_MIN = 1000;
    private const LEBAR_MAKS = 2000;

    protected const KUNCI = 'surat.kop';
    protected const BAWAAN = 'assets/kop-surat.png';
    protected const NAMA = 'Kop surat';

    public static function pakaiTeks(): bool
    {
        return Pengaturan::ambil('surat.kop_mode') === 'teks';
    }

    public static function pilihMode(string $mode): void
    {
        Pengaturan::simpan('surat.kop_mode', $mode === 'teks' ? 'teks' : null);
    }

    /** Null berarti surat memakai kop teks. */
    public static function dataUri(): ?string
    {
        return self::pakaiTeks() ? null : parent::dataUri();
    }

    /** Diratakan ke latar putih dan diperkecil karena DomPDF lambat memproses PNG besar atau transparan. */
    protected function olah(GdImage $asal, int $lebar, int $tinggi): string
    {
        if ($lebar < self::LEBAR_MIN) {
            throw new InvalidArgumentException('Lebar gambar minimal '.self::LEBAR_MIN.' piksel agar kop tidak pecah saat dicetak. Gambar ini '.$lebar.' piksel.');
        }
        if ($lebar < $tinggi * 2) {
            throw new InvalidArgumentException('Kop surat harus berbentuk memanjang: lebarnya minimal dua kali tingginya. Potong ruang kosong di atas dan bawah gambar.');
        }

        $baru = min($lebar, self::LEBAR_MAKS);
        $tinggiBaru = max(1, (int) round($tinggi * $baru / $lebar));
        $hasil = imagecreatetruecolor($baru, $tinggiBaru);
        imagefill($hasil, 0, 0, imagecolorallocate($hasil, 255, 255, 255));
        imagecopyresampled($hasil, $asal, 0, 0, 0, 0, $baru, $tinggiBaru, $lebar, $tinggi);

        return self::keString($hasil);
    }
}
