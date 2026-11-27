<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Peran tambahan buatan Super Admin. Pengguna dengan peran ini memakai kewenangan
 * peran bawaan yang dipilih di hak_akses (users.role diisi nilai yang sama).
 */
class Peran extends Model
{
    protected $fillable = ['nama', 'hak_akses'];

    public function pengguna()
    {
        return $this->hasMany(User::class);
    }

    public function labelHakAkses(): string
    {
        return User::namaPeranBawaan()[$this->hak_akses] ?? $this->hak_akses;
    }
}
