<?php

namespace App\Http\Controllers;

use App\Models\Peran;
use App\Models\User;
use App\Services\LogoService;
use App\Support\Identitas;
use App\Support\PengaturanEmail;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PengaturanAplikasiController extends Controller
{
    private const KATEGORI_MAKS = 20;
    private const PANJANG_KATEGORI = 50;

    public function edit()
    {
        return view('pengaturan.aplikasi', [
            'identitas' => Identitas::semua(),
            'logoUnggahan' => LogoService::pakaiUnggahan(),
            'email' => PengaturanEmail::semua(),
            // Kata sandi SMTP tidak pernah dikirim ke browser; cukup penanda sudah tersimpan.
            'sandiTersimpan' => PengaturanEmail::sandi() !== null,
            'perans' => Peran::withCount('pengguna')->orderBy('nama')->get(),
            'jumlahBawaan' => User::whereNull('peran_id')->selectRaw('role, count(*) as jumlah')->groupBy('role')->pluck('jumlah', 'role'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_aplikasi' => ['required', 'string', 'max:30'],
            'nama_unit' => ['required', 'string', 'max:60'],
            'kategori_surat_masuk' => ['required', 'string', 'max:2000', function (string $isian, mixed $nilai, Closure $gagal) {
                $daftar = Identitas::baris($nilai);
                if (! $daftar || count($daftar) > self::KATEGORI_MAKS) {
                    $gagal('Isi 1 sampai '.self::KATEGORI_MAKS.' kategori, satu per baris.');
                } elseif (collect($daftar)->contains(fn ($k) => mb_strlen($k) > self::PANJANG_KATEGORI)) {
                    $gagal('Tiap kategori paling panjang '.self::PANJANG_KATEGORI.' karakter.');
                } elseif (count($daftar) !== count(array_unique(array_map('mb_strtolower', $daftar)))) {
                    $gagal('Ada kategori yang ditulis dua kali.');
                }
            }],
        ], [], ['nama_aplikasi' => 'nama aplikasi', 'nama_unit' => 'nama unit', 'kategori_surat_masuk' => 'kategori surat masuk']);

        Identitas::simpan($data);
        Log::info('Pengaturan aplikasi diubah', ['oleh' => $request->user()->id]);

        return back()->with('status', 'Pengaturan aplikasi disimpan.');
    }

    public function simpanLogo(Request $request, LogoService $logo): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048']], [], ['logo' => 'berkas logo']);
        try {
            $logo->simpan($request->file('logo')->get());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }
        Log::info('Logo diganti', ['oleh' => $request->user()->id]);

        return back()->with('status', 'Logo disimpan.');
    }

    public function hapusLogo(Request $request, LogoService $logo): RedirectResponse
    {
        $logo->hapus();
        Log::info('Logo dikembalikan ke bawaan', ['oleh' => $request->user()->id]);

        return back()->with('status', 'Logo dikembalikan ke bawaan.');
    }

    /** Dipakai halaman masuk dan validasi publik, jadi tidak memerlukan login. */
    public function logo()
    {
        abort_unless(LogoService::pakaiUnggahan(), 404);

        return response(LogoService::isiBerkas(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
