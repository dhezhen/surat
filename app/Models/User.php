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
        'name', 'email', 'password', 'jabatan', 'nidn', 'role', 'peran_id', 'signature_path', 'is_active', 'notif_email',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notif_email' => 'boolean',
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

    /** Gambar tanda tangan ditanam langsung di halaman agar tidak perlu URL publik (storage:link). */
    public function tandaTanganDataUri(): ?string
    {
        return $this->punyaTandaTangan()
            ? 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($this->signature_path))
            : null;
    }

    public function peran()
    {
        return $this->belongsTo(Peran::class);
    }

    /** Nama peran tambahan bila ada; kewenangan tetap mengikuti kolom role. */
    public function labelRole(): string
    {
        if ($this->peran_id && $this->peran) {
            return $this->peran->nama;
        }

        return self::namaPeranBawaan()[$this->role] ?? $this->role;
    }

    /**
     * Nama tampil peran bawaan. Super Admin boleh menggantinya; kodenya (staff_prodi dst.) tetap.
     *
     * @return array<string, string>
     */
    public static function namaPeranBawaan(): array
    {
        if (! app()->bound('peran.nama')) {
            $tersimpan = Pengaturan::where('kunci', 'like', 'peran.nama.%')->pluck('nilai', 'kunci');
            $nama = [];
            foreach (self::PERAN as $kode => $bawaan) {
                $nama[$kode] = $tersimpan["peran.nama.$kode"] ?? $bawaan;
            }
            app()->instance('peran.nama', $nama);
        }

        return app('peran.nama');
    }

    /** Nama kosong atau sama dengan aslinya berarti kembali ke nama asli. */
    public static function simpanNamaPeranBawaan(string $kode, ?string $nama): void
    {
        $nama = trim((string) $nama);
        Pengaturan::simpan("peran.nama.$kode", $nama === '' || $nama === self::PERAN[$kode] ? null : $nama);
        app()->forgetInstance('peran.nama');
    }

    /** Nilai pilihan peran pada form pengguna: kode peran bawaan, atau "peran:{id}" untuk peran tambahan. */
    public function kunciPeran(): ?string
    {
        return $this->peran_id ? "peran:{$this->peran_id}" : $this->role;
    }

    /** @return array<string, string> */
    public static function pilihanPeran(): array
    {
        $pilihan = self::namaPeranBawaan();
        foreach (Peran::orderBy('nama')->get() as $peran) {
            $pilihan["peran:{$peran->id}"] = $peran->nama.' (akses '.$peran->labelHakAkses().')';
        }

        return $pilihan;
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
