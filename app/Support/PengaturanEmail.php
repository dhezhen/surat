<?php

namespace App\Support;

use App\Models\Pengaturan;
use Throwable;

/**
 * Pengaturan SMTP dari halaman Pengaturan Aplikasi. Selama belum diaktifkan,
 * pengiriman email memakai konfigurasi MAIL_* di .env.
 */
class PengaturanEmail
{
    public const ENKRIPSI = [
        'tls' => 'STARTTLS (umumnya port 587)',
        'ssl' => 'SSL/TLS (umumnya port 465)',
        'none' => 'Tanpa enkripsi (umumnya port 25)',
    ];

    private const KUNCI = ['aktif', 'host', 'port', 'enkripsi', 'username', 'dari_alamat', 'dari_nama'];

    /** Tanpa kata sandi; kata sandi hanya dibaca lewat sandi(). */
    public static function semua(): array
    {
        $tersimpan = Pengaturan::where('kunci', 'like', 'email.%')->pluck('nilai', 'kunci');
        $hasil = [];
        foreach (self::KUNCI as $kunci) {
            $hasil[$kunci] = $tersimpan['email.'.$kunci] ?? null;
        }
        $hasil['aktif'] = $hasil['aktif'] === '1';
        $hasil['port'] = $hasil['port'] !== null ? (int) $hasil['port'] : null;
        $hasil['enkripsi'] = array_key_exists((string) $hasil['enkripsi'], self::ENKRIPSI) ? $hasil['enkripsi'] : 'tls';

        return $hasil;
    }

    public static function sandi(): ?string
    {
        return Pengaturan::ambilRahasia('email.sandi');
    }

    public static function simpan(array $nilai): void
    {
        foreach (self::KUNCI as $kunci) {
            if (array_key_exists($kunci, $nilai)) {
                $isi = $nilai[$kunci];
                Pengaturan::simpan('email.'.$kunci, is_bool($isi) ? ($isi ? '1' : '0') : ($isi === null || $isi === '' ? null : (string) $isi));
            }
        }
    }

    public static function simpanSandi(?string $sandi): void
    {
        Pengaturan::simpanRahasia('email.sandi', $sandi);
    }

    /**
     * Menimpa konfigurasi mailer "smtp" dengan isian yang tersimpan.
     * $paksa dipakai uji kirim agar pengaturan bisa dicoba sebelum diaktifkan.
     */
    public static function terapkan(bool $paksa = false): bool
    {
        try {
            $s = self::semua();
            $sandi = self::sandi();
        } catch (Throwable) {
            // Database belum siap (mis. sebelum migrasi); tetap pakai konfigurasi .env.
            return false;
        }

        if (! $s['host'] || (! $s['aktif'] && ! $paksa)) {
            return false;
        }

        config([
            'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                'transport' => 'smtp',
                'url' => null,
                'scheme' => $s['enkripsi'] === 'ssl' ? 'smtps' : 'smtp',
                'host' => $s['host'],
                'port' => $s['port'] ?: ($s['enkripsi'] === 'ssl' ? 465 : 587),
                'username' => $s['username'] ?: null,
                'password' => $sandi,
                'timeout' => 15,
                'auto_tls' => $s['enkripsi'] !== 'none',
                'require_tls' => $s['enkripsi'] === 'tls',
            ]),
            'mail.from' => [
                'address' => $s['dari_alamat'] ?: ($s['username'] ?: config('mail.from.address')),
                'name' => $s['dari_nama'] ?: Identitas::ambil('nama_aplikasi'),
            ],
        ]);
        if ($s['aktif']) {
            config(['mail.default' => 'smtp']);
        }

        return true;
    }

    /** Dipanggil setelah pengaturan berubah agar mailer yang sudah dibuat ikut memakai isian baru. */
    public static function segarkan(bool $paksa = false): bool
    {
        $diterapkan = self::terapkan($paksa);
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }

        return $diterapkan;
    }
}
