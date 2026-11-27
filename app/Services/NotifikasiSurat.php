<?php

namespace App\Services;

use App\Models\SuratKeluar;
use App\Models\User;
use App\Notifications\PerubahanStatusSurat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Memberi tahu pihak yang perlu bertindak saat status surat berubah.
 * Notifikasi di aplikasi disimpan langsung. Email dikirim setelah respons selesai, sehingga
 * server surel yang lambat atau gagal tidak menghambat maupun menggagalkan persetujuan.
 */
class NotifikasiSurat
{
    public static function diajukan(SuratKeluar $surat, User $oleh): void
    {
        $kaprodi = User::where('role', User::ROLE_KAPRODI)->aktif()->get();
        self::kirim($kaprodi, new PerubahanStatusSurat($surat, PerubahanStatusSurat::DIAJUKAN, $oleh));
    }

    public static function disetujui(SuratKeluar $surat, User $oleh): void
    {
        self::kirim(self::pembuat($surat), new PerubahanStatusSurat($surat, PerubahanStatusSurat::DISETUJUI, $oleh));
    }

    public static function revisi(SuratKeluar $surat, User $oleh): void
    {
        self::kirim(self::pembuat($surat), new PerubahanStatusSurat($surat, PerubahanStatusSurat::REVISI, $oleh));
    }

    private static function pembuat(SuratKeluar $surat): Collection
    {
        $pembuat = $surat->pembuat()->first();

        return collect($pembuat?->is_active ? [$pembuat] : []);
    }

    private static function kirim(Collection $penerima, PerubahanStatusSurat $notifikasi): void
    {
        $penerima = $penerima->reject(fn (User $u) => $u->is($notifikasi->oleh))->values();
        if ($penerima->isEmpty()) {
            return;
        }

        Notification::sendNow($penerima, $notifikasi, ['database']);

        $penerimaEmail = $penerima->filter(fn (User $u) => $u->notif_email && $u->email)->values();
        if ($penerimaEmail->isNotEmpty()) {
            defer(function () use ($penerimaEmail, $notifikasi) {
                try {
                    Notification::sendNow($penerimaEmail, $notifikasi, ['mail']);
                } catch (Throwable $e) {
                    Log::warning('Email notifikasi surat gagal dikirim', [
                        'surat_id' => $notifikasi->surat->id,
                        'peristiwa' => $notifikasi->peristiwa,
                        'galat' => $e->getMessage(),
                    ]);
                }
            });
        }
    }
}
