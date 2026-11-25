<?php

namespace App\Services;

use App\Models\Pengaturan;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Gambar yang bisa diganti lewat Pengaturan, dengan berkas bawaan sebagai cadangan. */
abstract class GambarPengaturan
{
    protected const KUNCI = '';
    protected const BAWAAN = '';
    protected const NAMA = '';

    private const PIKSEL_MAKS = 16_000_000;

    /** Memeriksa ukuran sesuai jenis gambar lalu mengembalikan PNG yang siap disimpan. */
    abstract protected function olah(GdImage $asal, int $lebar, int $tinggi): string;

    public function simpan(string $biner): void
    {
        $info = @getimagesizefromstring($biner);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new InvalidArgumentException(static::NAMA.' harus berupa gambar PNG atau JPG.');
        }
        if ($info[0] * $info[1] > self::PIKSEL_MAKS) {
            throw new InvalidArgumentException('Resolusi gambar terlalu besar. Perkecil gambar lalu coba lagi.');
        }
        $asal = @imagecreatefromstring($biner);
        if (! $asal) {
            throw new InvalidArgumentException('Gambar tidak dapat dibaca.');
        }

        $path = 'pengaturan/'.str_replace('.', '-', static::KUNCI).'-'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $this->olah($asal, $info[0], $info[1]));

        $lama = Pengaturan::ambil(static::KUNCI);
        Pengaturan::simpan(static::KUNCI, $path);
        if ($lama) {
            Storage::disk('local')->delete($lama);
        }
    }

    public function hapus(): void
    {
        if ($lama = Pengaturan::ambil(static::KUNCI)) {
            Storage::disk('local')->delete($lama);
        }
        Pengaturan::simpan(static::KUNCI, null);
    }

    public static function pakaiUnggahan(): bool
    {
        $path = Pengaturan::ambil(static::KUNCI);

        return $path && Storage::disk('local')->exists($path);
    }

    public static function isiBerkas(): ?string
    {
        if (static::pakaiUnggahan()) {
            return Storage::disk('local')->get(Pengaturan::ambil(static::KUNCI));
        }

        return static::isiBawaan();
    }

    public static function dataUri(): ?string
    {
        return self::sebagaiDataUri(static::isiBerkas());
    }

    public static function bawaanDataUri(): ?string
    {
        return self::sebagaiDataUri(static::isiBawaan());
    }

    private static function isiBawaan(): ?string
    {
        $path = public_path(static::BAWAAN);

        return is_file($path) ? file_get_contents($path) : null;
    }

    private static function sebagaiDataUri(?string $biner): ?string
    {
        return $biner ? 'data:image/png;base64,'.base64_encode($biner) : null;
    }

    protected static function keString(GdImage $gambar): string
    {
        ob_start();
        imagepng($gambar, null, 6);

        return ob_get_clean();
    }
}
