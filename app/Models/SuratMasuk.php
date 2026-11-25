<?php

namespace App\Models;

use App\Support\Identitas;
use Illuminate\Database\Eloquent\Model;

class SuratMasuk extends Model
{
    protected $fillable = [
        'nomor_agenda', 'nomor_surat_asal', 'tanggal_surat', 'tanggal_diterima',
        'pengirim', 'perihal', 'kategori', 'keterangan', 'file_scan_path',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_diterima' => 'date',
        ];
    }

    /** Daftar dari Pengaturan, ditambah kategori lama milik arsip yang sedang diubah. @return list<string> */
    public static function pilihanKategori(?self $surat = null): array
    {
        return array_values(array_unique(array_filter([...Identitas::ambil('kategori_surat_masuk'), $surat?->kategori])));
    }

    /** Untuk penyaring daftar: kategori yang sudah dihapus tetap bisa dicari selama masih dipakai arsip. */
    public static function kategoriUntukSaring(): array
    {
        return array_values(array_unique([...self::pilihanKategori(), ...self::query()->distinct()->orderBy('kategori')->pluck('kategori')]));
    }

    public function scopeCari($query, ?string $q)
    {
        if (! $q) {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        return $query->where(fn ($w) => $w->where('nomor_agenda', 'like', $like)
            ->orWhere('nomor_surat_asal', 'like', $like)
            ->orWhere('pengirim', 'like', $like)
            ->orWhere('perihal', 'like', $like)
            ->orWhere('keterangan', 'like', $like));
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->file_scan_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']);
    }
}
