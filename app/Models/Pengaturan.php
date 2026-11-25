<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Penyimpanan pengaturan aplikasi (kunci-nilai). Nilai rahasia seperti kunci API disimpan terenkripsi. */
class Pengaturan extends Model
{
    protected $fillable = ['kunci', 'nilai'];

    public static function ambil(string $kunci, ?string $bawaan = null): ?string
    {
        return static::where('kunci', $kunci)->value('nilai') ?? $bawaan;
    }

    public static function simpan(string $kunci, ?string $nilai): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }

    public static function ambilRahasia(string $kunci): ?string
    {
        $nilai = static::ambil($kunci);
        if ($nilai === null || $nilai === '') {
            return null;
        }

        try {
            return Crypt::decryptString($nilai);
        } catch (DecryptException) {
            // APP_KEY berubah sejak nilai disimpan; anggap belum diatur agar Super Admin memasukkannya lagi.
            return null;
        }
    }

    public static function simpanRahasia(string $kunci, ?string $nilai): void
    {
        static::simpan($kunci, $nilai === null || $nilai === '' ? null : Crypt::encryptString($nilai));
    }
}
