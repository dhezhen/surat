<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_STAFF = 'staff_prodi';
    public const ROLE_KAPRODI = 'kaprodi';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const PERAN = [
        self::ROLE_STAFF => 'Sekretaris / Staff Prodi',
        self::ROLE_KAPRODI => 'Ketua Program Studi',
        self::ROLE_SUPER_ADMIN => 'Super Admin',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'jabatan', 'nidn', 'role', 'signature_path', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isKaprodi(): bool
    {
        return $this->role === self::ROLE_KAPRODI;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function punyaTandaTangan(): bool
    {
        return $this->signature_path && Storage::disk('public')->exists($this->signature_path);
    }

    public function labelRole(): string
    {
        return self::PERAN[$this->role] ?? $this->role;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function suratDibuat()
    {
        return $this->hasMany(SuratKeluar::class, 'pembuat_id');
    }

    public function suratDisetujui()
    {
        return $this->hasMany(SuratKeluar::class, 'penyetuju_id');
    }

    /** Akun yang sudah terkait surat tidak boleh dihapus agar riwayat penerbitan tetap utuh. */
    public function punyaRiwayatSurat(): bool
    {
        return $this->suratDibuat()->withTrashed()->exists() || $this->suratDisetujui()->withTrashed()->exists();
    }
}
