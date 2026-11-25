<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\KopSuratService;
use App\Services\LogoService;
use App\Services\PenomoranService;
use App\Services\TandaTanganService;
use App\Support\Identitas;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PengaturanSuratController extends Controller
{
    private const ATURAN_GAMBAR = ['required', 'file', 'mimes:png,jpg,jpeg', 'max:4096'];

    private const TOKEN_NOMOR = ['{urut}', '{prodi}', '{fakultas}', '{bulan}', '{tahun}'];

    public function edit()
    {
        $tahun = (int) now()->year;
        $terpakai = PenomoranService::urutTerpakai($tahun);
        $awal = PenomoranService::nomorAwal($tahun);

        return view('pengaturan.surat', [
            'identitas' => Identitas::semua(),
            'tahun' => $tahun,
            'minimal' => $terpakai + 1,
            // Nomor awal yang sudah terlewati tidak ditampilkan lagi agar form tetap bisa disimpan.
            'nomorAwal' => $awal > $terpakai + 1 ? $awal : null,
            'kop' => KopSuratService::dataUri(),
            'kopTeks' => KopSuratService::pakaiTeks(),
            'kopUnggahan' => KopSuratService::pakaiUnggahan(),
            'logoKop' => LogoService::dataUri(),
            'penandatangan' => User::where('role', User::ROLE_KAPRODI)->aktif()->orderBy('name')->get()->map(fn (User $u) => [
                'user' => $u,
                'gambar' => $u->tandaTanganDataUri(),
            ]),
        ]);
    }

    public function simpanIdentitas(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_universitas' => ['required', 'string', 'max:100'],
            'nama_fakultas' => ['required', 'string', 'max:100'],
            'nama_prodi' => ['required', 'string', 'max:100'],
            'alamat' => ['required', 'string', 'max:500', function (string $isian, mixed $nilai, Closure $gagal) {
                if (count(Identitas::baris($nilai)) > 5) {
                    $gagal('Alamat paling banyak 5 baris.');
                }
            }],
            'kota' => ['required', 'string', 'max:60'],
            'label_id_penandatangan' => ['required', 'string', 'max:20'],
        ], [], [
            'nama_universitas' => 'nama universitas', 'nama_fakultas' => 'nama fakultas', 'nama_prodi' => 'nama program studi',
            'label_id_penandatangan' => 'label nomor induk',
        ]);

        Identitas::simpan($data);
        Log::info('Identitas prodi diubah', ['oleh' => $request->user()->id]);

        return back()->with('status', 'Identitas prodi disimpan. Berlaku untuk surat yang disetujui setelah ini.');
    }

    public function simpanPenomoran(Request $request): RedirectResponse
    {
        $tahun = (int) now()->year;
        $minimal = PenomoranService::urutTerpakai($tahun) + 1;
        $kode = ['nullable', 'string', 'max:40', 'regex:/^[^\x00-\x1F<>{}]*$/u'];

        $data = $request->validate([
            'format_nomor' => ['required', 'string', 'max:80', 'regex:/^[^\x00-\x1F<>]+$/u', function (string $isian, mixed $nilai, Closure $gagal) {
                preg_match_all('/\{[^{}]*\}/', (string) $nilai, $cocok);
                if ($asing = array_diff($cocok[0], self::TOKEN_NOMOR)) {
                    $gagal('Penanda tidak dikenal: '.implode(', ', $asing).'.');
                }
                // Nomor urut mulai lagi dari 1 tiap tahun; tanpa tahun, nomor tahun depan akan kembar.
                if (! str_contains((string) $nilai, '{urut}') || ! str_contains((string) $nilai, '{tahun}')) {
                    $gagal('Format nomor wajib memuat {urut} dan {tahun}.');
                }
            }],
            'nomor_pad' => ['required', 'integer', 'between:0,6'],
            'kode_fakultas' => $kode,
            'kode_prodi' => $kode,
            'nomor_awal' => ['nullable', 'integer', 'min:'.$minimal, 'max:999999'],
        ], [
            'nomor_awal.min' => 'Nomor urut sampai '.($minimal - 1)." sudah terpakai pada {$tahun}. Isi {$minimal} atau lebih.",
        ], [
            'format_nomor' => 'format nomor', 'nomor_pad' => 'jumlah digit', 'nomor_awal' => 'nomor urut berikutnya',
        ]);

        Identitas::simpan([
            'format_nomor' => trim($data['format_nomor']),
            'nomor_pad' => $data['nomor_pad'],
            'kode_fakultas' => $data['kode_fakultas'] ?? null,
            'kode_prodi' => $data['kode_prodi'] ?? null,
        ]);
        PenomoranService::aturNomorAwal($tahun, isset($data['nomor_awal']) ? (int) $data['nomor_awal'] : null);
        Log::info('Penomoran surat diubah', ['oleh' => $request->user()->id] + $data);

        return back()->with('status', 'Penomoran disimpan. Nomor berikutnya: '.PenomoranService::perkiraan().'.');
    }

    public function modeKop(Request $request): RedirectResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in(['gambar', 'teks'])]])['mode'];
        KopSuratService::pilihMode($mode);
        Log::info('Jenis kop diubah', ['mode' => $mode, 'oleh' => $request->user()->id]);

        return back()->with('status', $mode === 'teks' ? 'Surat memakai kop teks.' : 'Surat memakai kop gambar.');
    }

    public function simpanKop(Request $request, KopSuratService $kop): RedirectResponse
    {
        $request->validate(['kop' => self::ATURAN_GAMBAR], [], ['kop' => 'berkas kop surat']);
        $this->olah('kop', fn () => $kop->simpan($request->file('kop')->get()));
        Log::info('Kop surat diganti', ['oleh' => $request->user()->id]);

        return back()->with('status', 'Kop surat disimpan. Berlaku untuk surat yang disetujui setelah ini.');
    }

    public function hapusKop(Request $request, KopSuratService $kop): RedirectResponse
    {
        $kop->hapus();
        Log::info('Kop surat dikembalikan ke bawaan', ['oleh' => $request->user()->id]);

        return back()->with('status', 'Kop surat dikembalikan ke bawaan.');
    }

    public function simpanTandaTangan(Request $request, User $pengguna, TandaTanganService $service): RedirectResponse
    {
        abort_unless($pengguna->isKaprodi(), 404);
        $request->validate(['tanda_tangan' => self::ATURAN_GAMBAR], [], ['tanda_tangan' => 'berkas tanda tangan']);
        $this->olah('tanda_tangan', fn () => $service->simpan($pengguna, $request->file('tanda_tangan')->get()));
        // Dicatat karena tanda tangan seseorang diganti oleh akun lain.
        Log::info('Tanda tangan diunggah admin', ['pemilik' => $pengguna->id, 'oleh' => $request->user()->id]);

        return back()->with('status', 'Tanda tangan '.$pengguna->name.' disimpan.');
    }

    public function hapusTandaTangan(Request $request, User $pengguna, TandaTanganService $service): RedirectResponse
    {
        abort_unless($pengguna->isKaprodi(), 404);
        $service->hapus($pengguna);
        Log::info('Tanda tangan dihapus admin', ['pemilik' => $pengguna->id, 'oleh' => $request->user()->id]);

        return back()->with('status', 'Tanda tangan '.$pengguna->name.' dihapus.');
    }

    /** Penolakan dari pengolah gambar ditampilkan sebagai galat pada isian yang bersangkutan. */
    private function olah(string $isian, callable $aksi): void
    {
        try {
            $aksi();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$isian => $e->getMessage()]);
        }
    }
}
