<?php

namespace App\Http\Controllers;

use App\Models\SuratMasuk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SuratMasukController extends Controller
{
    public function index(Request $request)
    {
        $surats = SuratMasuk::cari($request->q)
            ->when($request->kategori, fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->tahun, fn ($q, $t) => $q->whereYear('tanggal_diterima', $t))
            ->latest('tanggal_diterima')->latest('id')
            ->paginate(15)->withQueryString();

        return view('surat-masuk.index', compact('surats'));
    }

    public function create()
    {
        return view('surat-masuk.form', ['surat' => new SuratMasuk(['tanggal_diterima' => now(), 'tanggal_surat' => now()])]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, true);
        $path = $request->file('file_scan')->store('surat-masuk', 'public');

        // Diantrekan agar dua pencatatan bersamaan tidak berebut nomor agenda.
        $surat = Cache::lock('nomor-agenda-masuk', 10)->block(10, fn () => DB::transaction(function () use ($data, $path) {
            $data['nomor_agenda'] = $this->nomorAgenda($data['tanggal_diterima']);
            $data['file_scan_path'] = $path;

            return SuratMasuk::create($data);
        }));

        return redirect()->route('surat-masuk.show', $surat)->with('status', 'Surat masuk diarsipkan dengan nomor agenda '.$surat->nomor_agenda.'.');
    }

    public function show(SuratMasuk $surat)
    {
        return view('surat-masuk.show', compact('surat'));
    }

    public function edit(SuratMasuk $surat)
    {
        return view('surat-masuk.form', compact('surat'));
    }

    public function update(Request $request, SuratMasuk $surat)
    {
        $data = $this->validasi($request, false, $surat);

        if ($request->hasFile('file_scan')) {
            $baru = $request->file('file_scan')->store('surat-masuk', 'public');
            Storage::disk('public')->delete($surat->file_scan_path);
            $data['file_scan_path'] = $baru;
        }
        $surat->update($data);

        return redirect()->route('surat-masuk.show', $surat)->with('status', 'Arsip surat masuk diperbarui.');
    }

    public function destroy(SuratMasuk $surat)
    {
        Storage::disk('public')->delete($surat->file_scan_path);
        $surat->delete();

        return redirect()->route('surat-masuk.index')->with('status', 'Arsip surat masuk dihapus.');
    }

    /** Menyajikan berkas scan hanya untuk pengguna yang sudah login (tanpa URL publik). */
    public function berkas(SuratMasuk $surat)
    {
        abort_unless(Storage::disk('public')->exists($surat->file_scan_path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk('public')->response($surat->file_scan_path);
    }

    private function nomorAgenda(string $tanggalDiterima): string
    {
        $tahun = (int) date('Y', strtotime($tanggalDiterima));
        $prefix = "SM-{$tahun}-";

        $terakhir = SuratMasuk::where('nomor_agenda', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('nomor_agenda')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($terakhir + 1), 3, '0', STR_PAD_LEFT);
    }

    private function validasi(Request $request, bool $wajibFile, ?SuratMasuk $surat = null): array
    {
        return $request->validate([
            'nomor_surat_asal' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'tanggal_diterima' => ['required', 'date', 'after_or_equal:tanggal_surat'],
            'pengirim' => ['required', 'string', 'max:255'],
            'perihal' => ['required', 'string', 'max:500'],
            'kategori' => ['required', Rule::in(SuratMasuk::pilihanKategori($surat))],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'file_scan' => [$wajibFile ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'tanggal_diterima.after_or_equal' => 'Tanggal diterima tidak boleh lebih awal dari tanggal surat.',
            'file_scan.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'file_scan.max' => 'Ukuran berkas maksimal 10 MB.',
        ]) + [];
    }
}
