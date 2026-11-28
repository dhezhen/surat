<?php

namespace App\Http\Controllers;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Services\Ai\AiService;
use App\Services\ArsipSurat;
use App\Services\NotifikasiSurat;
use App\Services\PenomoranService;
use App\Services\SuratRenderer;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuratKeluarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $surats = SuratKeluar::with(['jenisSurat', 'pembuat'])
            ->visibleTo($user)
            ->cari($request->q)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->tahun, fn ($q, $t) => $q->whereYear('tanggal_surat', $t))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('surat-keluar.index', compact('surats'));
    }

    public function create()
    {
        return view('surat-keluar.form', $this->formData(new SuratKeluar([
            'tanggal_surat' => now(),
            'data_dinamis' => [],
        ])));
    }

    public function store(Request $request)
    {
        [$data, $dinamis] = $this->validasi($request);

        $surat = new SuratKeluar($data);
        $surat->data_dinamis = $dinamis;
        $surat->pembuat_id = $request->user()->id;
        $surat->status = $request->input('aksi') === 'ajukan' ? SuratKeluar::MENUNGGU : SuratKeluar::DRAFT;
        $surat->save();
        if ($surat->status === SuratKeluar::MENUNGGU) {
            NotifikasiSurat::diajukan($surat, $request->user());
        }

        return redirect()->route('surat-keluar.show', $surat)->with('status',
            $surat->status === SuratKeluar::MENUNGGU ? 'Surat diajukan ke Ketua Prodi.' : 'Draf surat disimpan.');
    }

    public function show(Request $request, SuratKeluar $surat)
    {
        $this->pastikanBisaDilihat($request, $surat);
        $surat->load(['jenisSurat', 'pembuat', 'penyetuju']);

        if ($surat->isManual()) {
            return view('arsip.show', compact('surat'));
        }

        return view('surat-keluar.show', ['surat' => $surat] + SuratRenderer::kertas($surat));
    }

    public function edit(SuratKeluar $surat)
    {
        abort_unless($surat->isEditable(), 403, 'Surat yang sudah diajukan atau terbit tidak dapat diubah.');

        return view('surat-keluar.form', $this->formData($surat));
    }

    public function update(Request $request, SuratKeluar $surat)
    {
        abort_unless($surat->isEditable(), 403, 'Surat yang sudah diajukan atau terbit tidak dapat diubah.');

        [$data, $dinamis] = $this->validasi($request);

        $surat->fill($data);
        $surat->data_dinamis = $dinamis;
        $diajukan = $request->input('aksi') === 'ajukan';
        if ($diajukan) {
            $surat->status = SuratKeluar::MENUNGGU;
        }
        $surat->save();
        if ($diajukan) {
            NotifikasiSurat::diajukan($surat, $request->user());
        }

        return redirect()->route('surat-keluar.show', $surat)->with('status',
            $surat->status === SuratKeluar::MENUNGGU ? 'Surat diajukan ke Ketua Prodi.' : 'Perubahan disimpan.');
    }

    /**
     * Staff hanya boleh menghapus draf dan surat yang diminta revisi. Surat yang sedang diperiksa atau
     * sudah terbit hanya boleh dihapus Super Admin. Penghapusan bersifat lunak sehingga bisa dipulihkan.
     */
    public function destroy(Request $request, SuratKeluar $surat)
    {
        $user = $request->user();
        $boleh = $user->isSuperAdmin() || ($user->isStaff() && $surat->isEditable());
        abort_unless($boleh, 403, 'Surat yang sudah diajukan atau terbit hanya dapat dihapus oleh Super Admin.');

        $pesan = $surat->isTerbit()
            ? "Surat bernomor {$surat->nomor_surat_resmi} dihapus. Nomornya tidak dipakai ulang dan tautan validasinya tidak lagi berlaku. Surat dapat dipulihkan dari menu Surat Terhapus."
            : 'Surat dihapus.';
        $surat->delete();

        return redirect()->route('surat-keluar.index')->with('status', $pesan);
    }

    /**
     * Mengoreksi teks nomor surat yang sudah terbit (mis. salah ketik atau penyesuaian format).
     * Nomor urut internal tidak berubah, sehingga penomoran berikutnya tetap berlanjut.
     */
    public function ubahNomor(Request $request, SuratKeluar $surat): RedirectResponse
    {
        abort_unless($surat->isTerbit(), 403, 'Nomor hanya dapat diubah pada surat yang sudah terbit.');

        $data = $request->validate([
            'nomor_surat_resmi' => ['required', 'string', 'max:100', 'regex:/^[^\x00-\x1F<>]+$/u', Rule::unique('surat_keluars', 'nomor_surat_resmi')->ignore($surat->id)],
        ], [
            'nomor_surat_resmi.required' => 'Nomor surat wajib diisi.',
            'nomor_surat_resmi.regex' => 'Nomor surat memuat karakter yang tidak diizinkan.',
            'nomor_surat_resmi.unique' => 'Nomor tersebut sudah dipakai surat lain (termasuk surat yang dihapus).',
        ]);
        $baru = trim(preg_replace('/\s+/', ' ', $data['nomor_surat_resmi']));

        $lama = $surat->nomor_surat_resmi;
        if ($baru !== $lama) {
            $surat->forceFill(['nomor_surat_resmi' => $baru])->save();
            Log::info('Nomor surat diubah', ['surat_id' => $surat->id, 'dari' => $lama, 'menjadi' => $baru, 'oleh' => $request->user()->id]);
        }

        return back()->with('status', 'Nomor surat diubah menjadi '.$baru.'. Unduh ulang PDF agar cetakan memakai nomor baru.');
    }

    public function aturPdfPublik(Request $request, SuratKeluar $surat): RedirectResponse
    {
        abort_unless($surat->isTerbit(), 403, 'Pengaturan ini hanya untuk surat yang sudah terbit.');

        $tampil = (bool) $request->validate(['tampil_pdf_publik' => ['required', 'boolean']])['tampil_pdf_publik'];
        $surat->forceFill(['tampil_pdf_publik' => $tampil])->save();
        Log::info('Tampilan PDF publik diubah', ['surat_id' => $surat->id, 'tampil' => $tampil, 'oleh' => $request->user()->id]);

        return back()->with('status', $tampil
            ? 'PDF surat sekarang tampil di halaman validasi publik.'
            : 'PDF surat disembunyikan dari halaman validasi publik.');
    }

    public function terhapus()
    {
        return view('surat-keluar.terhapus', [
            'surats' => SuratKeluar::onlyTrashed()->with(['jenisSurat', 'pembuat'])->latest('deleted_at')->paginate(15),
        ]);
    }

    public function pulihkan(SuratKeluar $surat): RedirectResponse
    {
        $surat->restore();

        return redirect()->route('surat-keluar.show', $surat)->with('status', 'Surat dipulihkan.');
    }

    public function ajukan(Request $request, SuratKeluar $surat)
    {
        abort_unless($surat->isEditable(), 403);
        $surat->update(['status' => SuratKeluar::MENUNGGU]);
        NotifikasiSurat::diajukan($surat, $request->user());

        return back()->with('status', 'Surat diajukan ke Ketua Prodi.');
    }

    public function setujui(Request $request, SuratKeluar $surat, PenomoranService $penomoran)
    {
        $data = $request->validate([
            'metode_ttd' => ['nullable', Rule::in(array_keys(SuratKeluar::METODE_TTD))],
            'tampil_pdf_publik' => ['nullable', 'boolean'],
        ], [], ['metode_ttd' => 'metode tanda tangan']);
        $tampilPdf = isset($data['tampil_pdf_publik']) ? (bool) $data['tampil_pdf_publik'] : null;

        try {
            $surat = $penomoran->terbitkan($surat, $request->user(), $data['metode_ttd'] ?? null, $tampilPdf);
        } catch (\DomainException $e) {
            return back()->withErrors(['aksi' => $e->getMessage()]);
        }
        ArsipSurat::ambil($surat->load(['jenisSurat', 'penyetuju']));
        NotifikasiSurat::disetujui($surat, $request->user());

        return redirect()->route('surat-keluar.show', $surat)
            ->with('status', 'Surat disetujui dan terbit dengan nomor '.$surat->nomor_surat_resmi.'.');
    }

    public function revisi(Request $request, SuratKeluar $surat)
    {
        $request->validate(['catatan_revisi' => ['required', 'string', 'max:2000']], [
            'catatan_revisi.required' => 'Catatan revisi wajib diisi.',
        ]);
        abort_unless($surat->status === SuratKeluar::MENUNGGU, 403);

        $surat->update(['status' => SuratKeluar::REVISI, 'catatan_revisi' => $request->catatan_revisi]);
        NotifikasiSurat::revisi($surat, $request->user());

        return redirect()->route('dashboard')->with('status', 'Surat dikembalikan ke Staff Prodi untuk direvisi.');
    }

    public function pdf(Request $request, SuratKeluar $surat)
    {
        $this->pastikanBisaDilihat($request, $surat);
        abort_unless($surat->isTerbit(), 403, 'PDF hanya tersedia untuk surat yang sudah terbit.');

        return SuratRenderer::pdf($surat, $request->boolean('unduh', true));
    }

    private function formData(SuratKeluar $surat): array
    {
        $jenis = JenisSurat::where('is_active', true)->orderBy('nama_surat')->get();
        if ($surat->jenis_surat_id && ! $jenis->contains('id', $surat->jenis_surat_id)) {
            $jenis->push($surat->jenisSurat);
        }

        return [
            'surat' => $surat,
            'jenisList' => $jenis->map(fn ($j) => [
                'id' => $j->id,
                'nama' => $j->nama_surat,
                'template' => $j->template_html,
                'schema' => $j->form_schema,
            ])->values(),
            'penandatangan' => User::where('role', User::ROLE_KAPRODI)->aktif()->first(),
            'nomorPerkiraan' => $surat->nomor_surat_resmi ?: PenomoranService::perkiraan(),
            'aiTersedia' => AiService::tersedia(),
        ];
    }

    /** @return array{0: array, 1: array} atribut dasar dan isian dinamis yang sudah tervalidasi */
    private function validasi(Request $request): array
    {
        $base = $request->validate([
            'jenis_surat_id' => ['required', Rule::exists('jenis_surats', 'id')->where('is_active', true)],
            'tanggal_surat' => ['required', 'date'],
            'tujuan_surat' => ['required', 'string', 'max:255'],
            'perihal' => ['required', 'string', 'max:255'],
            'metode_ttd' => ['nullable', Rule::in(array_keys(SuratKeluar::METODE_TTD))],
        ], [], ['metode_ttd' => 'metode tanda tangan']);

        $jenis = JenisSurat::findOrFail($base['jenis_surat_id']);
        $this->uraikanTabel($request, $jenis);
        $rules = [];
        $labels = [];
        foreach ($jenis->form_schema as $f) {
            $key = 'dinamis.'.$f['name'];
            $labels[$key] = $f['label'] ?? $f['name'];

            if (($f['type'] ?? 'text') === 'tabel') {
                $rules[$key] = ['nullable', 'array'];
                $rules[$key.'.kolom'] = ['nullable', 'array', 'max:8'];
                $rules[$key.'.kolom.*'] = ['nullable', 'string', 'max:60'];
                $rules[$key.'.baris'] = ['nullable', 'array', 'max:300'];
                $rules[$key.'.baris.*'] = ['nullable', 'array', 'max:8'];
                $rules[$key.'.baris.*.*'] = ['nullable', 'string', 'max:255'];
                continue;
            }

            $r = [! empty($f['required']) && empty($f['default']) ? 'required' : 'nullable'];
            $r[] = match ($f['type'] ?? 'text') {
                'date' => 'date',
                'number' => 'numeric',
                'select' => Rule::in($f['options'] ?? []),
                default => 'string',
            };
            if (in_array($f['type'] ?? 'text', ['text', 'textarea', 'list', 'richtext'])) {
                $r[] = 'max:'.match ($f['type'] ?? 'text') {
                    'text' => 255,
                    'richtext' => 50000,
                    default => 3000,
                };
            }
            $rules[$key] = $r;
        }

        $validated = $request->validate($rules, [], $labels);
        $dinamis = [];
        foreach ($jenis->form_schema as $f) {
            $nilai = $validated['dinamis'][$f['name']] ?? '';
            if (($f['type'] ?? 'text') === 'tabel') {
                $dinamis[$f['name']] = $this->rapikanTabel(is_array($nilai) ? $nilai : [], $f);
                if (! empty($f['required']) && ! $dinamis[$f['name']]['baris']) {
                    throw ValidationException::withMessages(['dinamis.'.$f['name'] => ($f['label'] ?? $f['name']).' wajib berisi minimal satu baris.']);
                }
                continue;
            }
            if (($f['type'] ?? 'text') === 'richtext') {
                $nilai = HtmlSanitizer::bersihkan((string) $nilai);
            }
            $dinamis[$f['name']] = $nilai !== '' ? $nilai : ($f['default'] ?? '');
        }

        return [$base, $dinamis];
    }

    /**
     * Form mengirim tiap tabel sebagai satu string JSON. Bila dikirim per sel, PHP memotong data yang
     * melewati max_input_vars tanpa galat. JSON yang rusak dibiarkan agar ditolak validasi.
     */
    private function uraikanTabel(Request $request, JenisSurat $jenis): void
    {
        $dinamis = $request->input('dinamis');
        if (! is_array($dinamis)) {
            return;
        }

        foreach ($jenis->form_schema as $f) {
            $nilai = $dinamis[$f['name']] ?? null;
            if (($f['type'] ?? 'text') === 'tabel' && is_string($nilai) && is_array($terurai = json_decode($nilai, true))) {
                $dinamis[$f['name']] = $terurai;
            }
        }

        $request->merge(['dinamis' => $dinamis]);
    }

    /**
     * Menyeragamkan isian tabel: judul kolom kosong diberi nama bawaan, tiap baris disamakan jumlah selnya
     * dengan jumlah kolom, dan baris yang seluruhnya kosong dibuang.
     *
     * @return array{kolom: list<string>, baris: list<list<string>>}
     */
    private function rapikanTabel(array $nilai, array $field): array
    {
        $kolom = array_values(array_map(fn ($k) => trim((string) $k), $nilai['kolom'] ?? ($field['kolom'] ?? [])));
        foreach ($kolom as $i => $k) {
            $kolom[$i] = $k !== '' ? $k : 'Kolom '.($i + 1);
        }

        $baris = [];
        foreach (($nilai['baris'] ?? []) as $b) {
            $sel = array_map(fn ($c) => trim((string) $c), array_values((array) $b));
            $sel = array_slice(array_pad($sel, count($kolom), ''), 0, count($kolom));
            if (array_filter($sel, fn ($c) => $c !== '')) {
                $baris[] = $sel;
            }
        }

        return ['kolom' => $kolom, 'baris' => $baris];
    }

    private function pastikanBisaDilihat(Request $request, SuratKeluar $surat): void
    {
        abort_if($request->user()->isKaprodi() && $surat->status === SuratKeluar::DRAFT, 403, 'Draf belum diajukan.');
    }
}
