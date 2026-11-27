<?php

namespace App\Http\Controllers;

use App\Support\Identitas;
use App\Support\PengaturanEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PengaturanEmailController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $aktif = $request->boolean('aktif');
        $data = $request->validateWithBag('email', [
            'host' => [Rule::requiredIf($aktif), 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => [Rule::requiredIf($aktif), 'nullable', 'integer', 'between:1,65535'],
            'enkripsi' => ['required', Rule::in(array_keys(PengaturanEmail::ENKRIPSI))],
            'username' => ['nullable', 'string', 'max:255'],
            'sandi' => ['nullable', 'string', 'max:255'],
            'dari_alamat' => [Rule::requiredIf($aktif), 'nullable', 'email', 'max:255'],
            'dari_nama' => ['nullable', 'string', 'max:100'],
        ], [
            'host.regex' => 'Alamat server SMTP hanya berisi nama host, mis. smtp.gmail.com (tanpa http:// atau port).',
        ], [
            'host' => 'server SMTP', 'port' => 'port', 'enkripsi' => 'enkripsi', 'username' => 'nama pengguna',
            'sandi' => 'kata sandi', 'dari_alamat' => 'alamat pengirim', 'dari_nama' => 'nama pengirim',
        ]);

        PengaturanEmail::simpan([
            'host' => trim((string) ($data['host'] ?? '')),
            'port' => $data['port'] ?? null,
            'enkripsi' => $data['enkripsi'],
            'username' => trim((string) ($data['username'] ?? '')),
            'dari_alamat' => $data['dari_alamat'] ?? null,
            'dari_nama' => trim((string) ($data['dari_nama'] ?? '')),
            'aktif' => $aktif,
        ]);
        if ($request->boolean('hapus_sandi')) {
            PengaturanEmail::simpanSandi(null);
        } elseif (($data['sandi'] ?? '') !== '') {
            PengaturanEmail::simpanSandi($data['sandi']);
        }
        PengaturanEmail::segarkan();
        Log::info('Pengaturan email diubah', ['oleh' => $request->user()->id, 'aktif' => $aktif]);

        return back()->with('status', $aktif
            ? 'Pengaturan email disimpan. Email dikirim lewat '.$data['host'].'.'
            : 'Pengaturan email disimpan. SMTP belum diaktifkan, jadi email masih memakai konfigurasi .env.');
    }

    /** Mengirim email uji ke akun Super Admin yang sedang masuk, memakai isian yang sudah tersimpan. */
    public function uji(Request $request): RedirectResponse
    {
        if (! PengaturanEmail::segarkan(true)) {
            throw ValidationException::withMessages(['uji' => 'Simpan alamat server SMTP terlebih dahulu.'])->errorBag('email');
        }

        $tujuan = $request->user()->email;
        $nama = Identitas::ambil('nama_aplikasi');
        try {
            Mail::mailer('smtp')->raw(
                "Email ini dikirim dari halaman Pengaturan Aplikasi {$nama} untuk menguji pengaturan SMTP.\nBila Anda menerimanya, pengaturan sudah benar.",
                fn ($pesan) => $pesan->to($tujuan)->subject("Uji pengaturan email {$nama}")
            );
        } catch (Throwable $e) {
            Log::warning('Uji email SMTP gagal', ['oleh' => $request->user()->id, 'galat' => $e->getMessage()]);

            return back()->withErrors(['uji' => Str::limit($e->getMessage(), 300)], 'email');
        } finally {
            // Kembalikan ke keadaan tersimpan bila SMTP belum diaktifkan.
            PengaturanEmail::segarkan();
        }

        return back()->with('status', "Email uji terkirim ke {$tujuan}. Periksa kotak masuk (dan folder spam).");
    }
}
