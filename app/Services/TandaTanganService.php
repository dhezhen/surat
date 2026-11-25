<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TandaTanganService
{
    private const LEBAR_MAKS = 700;
    private const PIKSEL_MAKS = 16_000_000;

    /**
     * Mengolah gambar tanda tangan (foto, scan, atau hasil gambar di layar): latar putih dibuat
     * transparan, tepi kosong dipotong, lalu disimpan sebagai PNG dan menggantikan berkas lama.
     */
    public function simpan(User $user, string $biner): string
    {
        $png = $this->olah($biner);

        $path = 'tanda-tangan/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $png);

        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }
        $user->update(['signature_path' => $path]);

        return $path;
    }

    public function hapus(User $user): void
    {
        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }
        $user->update(['signature_path' => null]);
    }

    public function olah(string $biner): string
    {
        $info = @getimagesizefromstring($biner);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new InvalidArgumentException('Berkas harus berupa gambar PNG atau JPG.');
        }
        if ($info[0] * $info[1] > self::PIKSEL_MAKS) {
            throw new InvalidArgumentException('Resolusi gambar terlalu besar. Perkecil gambar lalu coba lagi.');
        }

        $asal = imagecreatefromstring($biner);
        if (! $asal) {
            throw new InvalidArgumentException('Gambar tidak dapat dibaca.');
        }

        $w = imagesx($asal);
        $h = imagesy($asal);
        $skala = min(1, self::LEBAR_MAKS / $w);
        $lebar = max(1, (int) round($w * $skala));
        $tinggi = max(1, (int) round($h * $skala));

        // Ratakan di atas putih dulu supaya piksel transparan dari kanvas tidak terbaca hitam.
        $rata = imagecreatetruecolor($lebar, $tinggi);
        imagefill($rata, 0, 0, imagecolorallocate($rata, 255, 255, 255));
        imagecopyresampled($rata, $asal, 0, 0, 0, 0, $lebar, $tinggi, $w, $h);
        imagedestroy($asal);

        $hasil = imagecreatetruecolor($lebar, $tinggi);
        imagealphablending($hasil, false);
        imagesavealpha($hasil, true);

        [$minX, $minY, $maxX, $maxY] = [$lebar, $tinggi, -1, -1];
        for ($y = 0; $y < $tinggi; $y++) {
            for ($x = 0; $x < $lebar; $x++) {
                $c = imagecolorat($rata, $x, $y);
                $r = ($c >> 16) & 255;
                $g = ($c >> 8) & 255;
                $b = $c & 255;
                $terang = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);

                // Putih jadi transparan; makin gelap makin pekat.
                $alpha = max(0, min(127, $terang - 128));
                imagesetpixel($hasil, $x, $y, imagecolorallocatealpha($hasil, $r, $g, $b, $alpha));

                if ($alpha < 100) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }
        imagedestroy($rata);

        if ($maxX < 0) {
            imagedestroy($hasil);
            throw new InvalidArgumentException('Tanda tangan tidak terdeteksi pada gambar. Pastikan goresan terlihat jelas.');
        }

        $pad = 4;
        $potong = imagecrop($hasil, [
            'x' => max(0, $minX - $pad),
            'y' => max(0, $minY - $pad),
            'width' => min($lebar, $maxX + $pad + 1) - max(0, $minX - $pad),
            'height' => min($tinggi, $maxY + $pad + 1) - max(0, $minY - $pad),
        ]);
        imagedestroy($hasil);

        imagesavealpha($potong, true);
        ob_start();
        imagepng($potong, null, 6);
        $png = ob_get_clean();
        imagedestroy($potong);

        return $png;
    }
}
