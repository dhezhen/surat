<?php

namespace App\Notifications;

use App\Models\SuratKeluar;
use App\Models\User;
use App\Support\Identitas;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PerubahanStatusSurat extends Notification
{
    public const DIAJUKAN = 'diajukan';
    public const DISETUJUI = 'disetujui';
    public const REVISI = 'revisi';

    public function __construct(
        public SuratKeluar $surat,
        public string $peristiwa,
        public User $oleh,
    ) {}

    /** Kanal dipilih pengirim (lihat NotifikasiSurat); ini hanya cadangan. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function judul(): string
    {
        return match ($this->peristiwa) {
            self::DIAJUKAN => 'Surat menunggu persetujuan',
            self::DISETUJUI => 'Surat disetujui dan terbit',
            self::REVISI => 'Surat perlu direvisi',
        };
    }

    public function pesan(): string
    {
        $perihal = '"'.$this->surat->perihal.'"';

        return match ($this->peristiwa) {
            self::DIAJUKAN => "{$this->oleh->name} mengajukan surat {$perihal}.",
            self::DISETUJUI => "Surat {$perihal} disetujui {$this->oleh->name} dengan nomor {$this->surat->nomor_surat_resmi}.",
            self::REVISI => "{$this->oleh->name} meminta revisi surat {$perihal}.",
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'surat_id' => $this->surat->id,
            'peristiwa' => $this->peristiwa,
            'judul' => $this->judul(),
            'pesan' => $this->pesan(),
            'catatan' => $this->peristiwa === self::REVISI ? $this->surat->catatan_revisi : null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject($this->judul().' — '.Identitas::ambil('nama_aplikasi'))
            ->greeting('Yth. '.$notifiable->name.',')
            ->line($this->pesan());

        if ($this->peristiwa === self::REVISI && $this->surat->catatan_revisi) {
            $pesan->line('Catatan revisi: '.$this->surat->catatan_revisi);
        }

        return $pesan
            ->action($this->peristiwa === self::DIAJUKAN ? 'Tinjau surat' : 'Lihat surat', route('surat-keluar.show', $this->surat))
            ->salutation('Pesan ini dikirim otomatis oleh '.Identitas::ambil('nama_aplikasi').'. Pemberitahuan email dapat dimatikan di menu Profil Akun.');
    }
}
