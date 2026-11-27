<?php

namespace App\Http\Controllers;

use App\Models\Peran;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Peran bawaan hanya bisa diganti namanya, karena alur surat bergantung pada kodenya.
 * Peran tambahan (mis. "Sekretaris Jurusan") memakai kewenangan salah satu peran bawaan.
 */
class PeranController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('peranBaru', [
            'nama_peran' => $this->aturanNama(),
            'hak_akses_peran' => ['required', Rule::in(array_keys(User::PERAN))],
        ], [], ['nama_peran' => 'nama peran', 'hak_akses_peran' => 'hak akses']);

        $peran = Peran::create(['nama' => trim($data['nama_peran']), 'hak_akses' => $data['hak_akses_peran']]);
        Log::info('Peran ditambahkan', ['oleh' => $request->user()->id, 'peran' => $peran->id]);

        return back()->with('status', "Peran {$peran->nama} ditambahkan.");
    }

    public function update(Request $request, Peran $peran): RedirectResponse
    {
        $data = $request->validateWithBag('peran', [
            'nama' => $this->aturanNama(kecuali: $peran),
            'hak_akses' => ['required', Rule::in(array_keys(User::PERAN))],
        ], [], ['nama' => 'nama peran', 'hak_akses' => 'hak akses']);

        // Mengganti hak akses peran yang sedang dipakai bisa melanggar aturan satu Ketua Prodi aktif
        // atau menghapus Super Admin terakhir, jadi pengguna dipindahkan satu per satu lewat Manajemen Pengguna.
        $dipakai = $peran->pengguna()->count();
        if ($data['hak_akses'] !== $peran->hak_akses && $dipakai > 0) {
            throw ValidationException::withMessages([
                'hak_akses' => "Hak akses {$peran->nama} tidak dapat diganti karena dipakai {$dipakai} pengguna. Pindahkan penggunanya ke peran lain terlebih dahulu.",
            ])->errorBag('peran');
        }

        $peran->update(['nama' => trim($data['nama']), 'hak_akses' => $data['hak_akses']]);
        Log::info('Peran diubah', ['oleh' => $request->user()->id, 'peran' => $peran->id]);

        return back()->with('status', "Peran {$peran->nama} diperbarui.");
    }

    public function ubahBawaan(Request $request, string $kode): RedirectResponse
    {
        abort_unless(array_key_exists($kode, User::PERAN), 404);

        $data = $request->validateWithBag('peran', [
            'nama' => $this->aturanNama(kecualiBawaan: $kode),
        ], [], ['nama' => 'nama peran']);

        User::simpanNamaPeranBawaan($kode, $data['nama']);
        Log::info('Nama peran bawaan diubah', ['oleh' => $request->user()->id, 'peran' => $kode]);

        return back()->with('status', 'Nama peran disimpan: '.User::namaPeranBawaan()[$kode].'.');
    }

    public function destroy(Request $request, Peran $peran): RedirectResponse
    {
        $dipakai = $peran->pengguna()->count();
        if ($dipakai > 0) {
            return back()->withErrors(['hapus' => "{$peran->nama} masih dipakai {$dipakai} pengguna. Ganti peran pengguna tersebut terlebih dahulu."], 'peran');
        }

        $peran->delete();
        Log::info('Peran dihapus', ['oleh' => $request->user()->id, 'peran' => $peran->id]);

        return back()->with('status', "Peran {$peran->nama} dihapus.");
    }

    /** Nama peran tidak boleh sama dengan peran lain, baik bawaan maupun tambahan. */
    private function aturanNama(?Peran $kecuali = null, ?string $kecualiBawaan = null): array
    {
        return ['required', 'string', 'max:50', function (string $isian, mixed $nilai, Closure $gagal) use ($kecuali, $kecualiBawaan) {
            $nama = mb_strtolower(trim((string) $nilai));

            $bawaan = collect(User::namaPeranBawaan())->except($kecualiBawaan)->map(fn ($n) => mb_strtolower($n));
            $tambahan = Peran::when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))->pluck('nama')->map(fn ($n) => mb_strtolower($n));

            if ($bawaan->contains($nama) || $tambahan->contains($nama)) {
                $gagal('Nama ini sudah dipakai peran lain.');
            }
        }];
    }
}
