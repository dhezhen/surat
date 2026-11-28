<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SuratKeluar extends Model
{
    use SoftDeletes;

    public const DRAFT = 'draft';
    public const MENUNGGU = 'menunggu_approval';
    public const REVISI = 'revisi';
    public const TERBIT = 'terbit';

    public const SUMBER_SISTEM = 'sistem';
    public const SUMBER_MANUAL = 'manual';

    public const TTD_BASAH = 'basah';
    public const TTD_QR = 'qr';
    public const TTD_DIGITAL = 'digital';

    public const METODE_TTD = [
        self::TTD_BASAH => [
            'label' => 'TTD manual (basah)',
            'keterangan' => 'Ruang tanda tangan dikosongkan. Surat dicetak, lalu ditandatangani dan dicap.',
            'pratinjau' => '[Ruang tanda tangan basah]',
        ],
        self::TTD_QR => [
            'label' => 'TTD QR Code',
            'keterangan' => 'QR Code verifikasi menggantikan tanda tangan.',
            'pratinjau' => '[QR Code muncul setelah disetujui]',
        ],
        self::TTD_DIGITAL => [
            'label' => 'TTD digital',
            'keterangan' => 'Gambar tanda tangan dari profil Ketua Prodi, disertai QR Code verifikasi.',
            'pratinjau' => '[Tanda tangan digital & QR Code muncul setelah disetujui]',
        ],
    ];

    protected $fillable = [
        'jenis_surat_id', 'pembuat_id', 'tanggal_surat', 'tujuan_surat', 'perihal',
        'data_dinamis', 'status', 'catatan_revisi', 'metode_ttd',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->uuid_validasi ??= (string) Str::uuid();
        });

        // Dicatat di sini agar semua jalur pengajuan dan persetujuan ikut terekam untuk laporan.
        static::saving(function (self $s) {
            if ($s->isDirty('status')) {
                match ($s->status) {
                    self::MENUNGGU => $s->diajukan_at = now(),
                    self::TERBIT => $s->disetujui_at = now(),
                    default => null,
                };
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tampil_pdf_publik' => 'boolean',
            'diajukan_at' => 'datetime',
            'disetujui_at' => 'datetime',
        ];
    }

    /** Surat terbit memakai pilihan tersimpan; sebelum terbit mengikuti pengaturan jenis surat. */
    public function tampilPdfPublik(): bool
    {
        return (bool) ($this->tampil_pdf_publik ?? (! $this->isTerbit() && $this->jenisSurat?->pdf_publik));
    }

    /** JSON disimpan tanpa escape unicode supaya pencarian LIKE bekerja untuk semua karakter. */
    protected function dataDinamis(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? json_decode($v, true) : [],
            set: fn ($v) => json_encode($v ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public function jenisSurat()
    {
        return $this->belongsTo(JenisSurat::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'pembuat_id');
    }

    public function penyetuju()
    {
        return $this->belongsTo(User::class, 'penyetuju_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::REVISI]);
    }

    public function isTerbit(): bool
    {
        return $this->status === self::TERBIT;
    }

    /** Arsip manual tidak punya isi yang dirender sistem; dokumennya adalah berkas PDF hasil pindai. */
    public function isManual(): bool
    {
        return $this->sumber === self::SUMBER_MANUAL;
    }

    public function dihapusOleh()
    {
        return $this->belongsTo(User::class, 'dihapus_oleh_id');
    }

    /** Metode yang dipilih; bila belum dipilih, ditentukan dari ketersediaan gambar tanda tangan. */
    public function metodeTtd(?User $penandatangan = null): string
    {
        return $this->metode_ttd
            ?? ($penandatangan?->punyaTandaTangan() ? self::TTD_DIGITAL : self::TTD_QR);
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::DRAFT => 'Draf',
            self::MENUNGGU => 'Menunggu Persetujuan',
            self::REVISI => 'Perlu Revisi',
            self::TERBIT => 'Terbit',
            default => $this->status,
        };
    }

    public function scopeCari($query, ?string $q)
    {
        if (! $q) {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        return $query->where(fn ($w) => $w->where('nomor_surat_resmi', 'like', $like)
            ->orWhere('perihal', 'like', $like)
            ->orWhere('tujuan_surat', 'like', $like)
            // MySQL membandingkan kolom JSON dengan peka huruf besar-kecil.
            ->orWhereRaw('LOWER(data_dinamis) LIKE LOWER(?)', [$like]));
    }

    /** Kaprodi hanya melihat surat yang sudah diajukan; draf milik staf tidak ditampilkan. */
    public function scopeVisibleTo($query, User $user)
    {
        return $user->isKaprodi() ? $query->where('status', '!=', self::DRAFT) : $query;
    }
}
