<?php

namespace App\Http\Controllers;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Arsip surat keluar yang sudah terbit, dikelola Super Admin. Surat dari sistem isinya sudah dibekukan,
 * jadi hanya metadatanya yang boleh diubah; arsip manual (surat yang terbit di luar sistem) bisa diubah penuh.
 */
class ArsipSuratController extends Controller
{
    private const DISK = 'local';
    private const FOLDER = 'arsip-manual';

    public function index(Request $request)
    {
        $terhapus = $request->query('tab') === 'terhapus';
        $sumber = in_array($request->query('sumber'), [SuratKeluar::SUMBER_SISTEM, SuratKeluar::SUMBER_MANUAL], true)
            ? $request->query('sumber') : null;

        $surats = SuratKeluar::with(['jenisSurat', 'penyetuju', 'dihapusOleh'])
            ->where('status', SuratKeluar::TERBIT)
            ->when($terhapus, fn ($q) => $q->onlyTrashed())
            ->cari($request->query('q'))
            ->when($request->integer('tahun'), fn ($q, $t) => $q->where('tahun_terbit', $t))
            ->when($request->integer('jenis'), fn ($q, $j) => $q->where('jenis_surat_id', $j))
            ->when($sumber, fn ($q, $s) => $q->where('sumber', $s))
            ->orderByDesc($terhapus ? 'deleted_at' : 'tanggal_surat')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('arsip.index', [
            'surats' => $surats,
            'terhapus' => $terhapus,
            'jumlahTerhapus' => SuratKeluar::onlyTrashed()->where('status', SuratKeluar::TERBIT)->count(),
            'daftarTahun' => SuratKeluar::withTrashed()->where('status', SuratKeluar::TERBIT)
                ->whereNotNull('tahun_terbit')->distinct()->orderByDesc('tahun_terbit')->pluck('tahun_terbit'),
            'daftarJenis' => JenisSurat::orderBy('nama_surat')->get(['id', 'nama_surat']),
        ]);
    }

    public function create()
    {
        return view('arsip.form', $this->dataForm((new SuratKeluar)->forceFill([
            'sumber' => SuratKeluar::SUMBER_MANUAL,
            'tampil_pdf_publik' => false,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->aturanManual(null, berkasWajib: true), $this->pesanNomor(), $this->label());
        $path = $request->file('berkas')->store(self::FOLDER, self::DISK);

        try {
            $surat = new SuratKeluar;
            $surat->forceFill($this->atributManual($data) + [
                'sumber' => SuratKeluar::SUMBER_MANUAL,
                'status' => SuratKeluar::TERBIT,
                'pembuat_id' => $request->user()->id,
                'metode_ttd' => SuratKeluar::TTD_BASAH,
                'berkas_path' => $path,
            ]);
            $surat->data_dinamis = [];
            $surat->save();
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }

        return redirect()->route('arsip.index')->with('status', "Arsip {$surat->nomor_surat_resmi} disimpan.");
    }

    public function edit(SuratKeluar $arsip)
    {
        abort_unless($arsip->isTerbit(), 404);

        return view('arsip.form', $this->dataForm($arsip->load(['jenisSurat', 'penyetuju'])));
    }

    public function update(Request $request, SuratKeluar $arsip): RedirectResponse
    {
        abort_unless($arsip->isTerbit(), 404);
        $nomorLama = $arsip->nomor_surat_resmi;

        if ($arsip->isManual()) {
            $data = $request->validate($this->aturanManual($arsip, berkasWajib: false), $this->pesanNomor(), $this->label());
            $atribut = $this->atributManual($data);
            $berkasLama = null;
            if ($request->hasFile('berkas')) {
                $berkasLama = $arsip->berkas_path;
                $atribut['berkas_path'] = $request->file('berkas')->store(self::FOLDER, self::DISK);
            }
            $arsip->forceFill($atribut)->save();
            if ($berkasLama) {
                Storage::disk(self::DISK)->delete($berkasLama);
            }
        } else {
            // Isi surat dari sistem sudah dibekukan; hanya nomor, tampilan PDF, dan keterangan yang boleh diubah.
            $data = $request->validate([
                'nomor_surat_resmi' => $this->aturanNomor($arsip),
                'alasan_koreksi' => ['nullable', 'string', 'max:255'],
                'tampil_pdf_publik' => ['required', 'boolean'],
                'keterangan_arsip' => ['nullable', 'string', 'max:2000'],
            ], $this->pesanNomor(), $this->label());
            $arsip->forceFill([
                'nomor_surat_resmi' => $this->rapikanNomor($data['nomor_surat_resmi']),
                'tampil_pdf_publik' => (bool) $data['tampil_pdf_publik'],
                'keterangan_arsip' => $data['keterangan_arsip'] ?? null,
            ])->save();
        }

        if ($arsip->nomor_surat_resmi !== $nomorLama) {
            Log::info('Nomor surat diubah', [
                'surat_id' => $arsip->id, 'dari' => $nomorLama, 'menjadi' => $arsip->nomor_surat_resmi,
                'alasan' => $request->input('alasan_koreksi'), 'oleh' => $request->user()->id,
            ]);
        }

        return redirect()->route('arsip.index')->with('status', "Arsip {$arsip->nomor_surat_resmi} diperbarui.");
    }

    public function destroy(Request $request, SuratKeluar $arsip): RedirectResponse
    {
        abort_unless($arsip->isTerbit(), 404);
        $data = $request->validate(['alasan_hapus' => ['required', 'string', 'max:255']], [
            'alasan_hapus.required' => 'Alasan penghapusan wajib diisi.',
        ]);

        $arsip->forceFill(['alasan_hapus' => $data['alasan_hapus'], 'dihapus_oleh_id' => $request->user()->id])->save();
        $arsip->delete();

        return redirect()->route('arsip.index')->with('status',
            "Arsip {$arsip->nomor_surat_resmi} dihapus. Nomornya tidak dipakai ulang dan tautan validasinya tidak berlaku. Pulihkan dari tab Terhapus bila perlu.");
    }

    public function pulihkan(SuratKeluar $arsip): RedirectResponse
    {
        abort_unless($arsip->trashed(), 404);
        $arsip->restore();
        $arsip->forceFill(['alasan_hapus' => null, 'dihapus_oleh_id' => null])->save();

        return redirect()->route('arsip.index')->with('status', "Arsip {$arsip->nomor_surat_resmi} dipulihkan.");
    }

    private function dataForm(SuratKeluar $surat): array
    {
        return [
            'surat' => $surat,
            'daftarJenis' => JenisSurat::orderBy('nama_surat')->get(['id', 'nama_surat']),
            // Termasuk Ketua Prodi nonaktif: arsip lama bisa ditandatangani pejabat sebelumnya.
            'daftarPenandatangan' => User::where('role', User::ROLE_KAPRODI)->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'jabatan', 'is_active']),
        ];
    }

    private function aturanManual(?SuratKeluar $surat, bool $berkasWajib): array
    {
        return [
            'nomor_surat_resmi' => $this->aturanNomor($surat),
            'tanggal_surat' => ['required', 'date', 'before_or_equal:today'],
            'jenis_surat_id' => ['required', Rule::exists('jenis_surats', 'id')],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_surat' => ['required', 'string', 'max:255'],
            'penyetuju_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_KAPRODI)],
            'berkas' => [$berkasWajib ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'tampil_pdf_publik' => ['required', 'boolean'],
            'keterangan_arsip' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function atributManual(array $data): array
    {
        return [
            'nomor_surat_resmi' => $this->rapikanNomor($data['nomor_surat_resmi']),
            'tanggal_surat' => $data['tanggal_surat'],
            'tahun_terbit' => (int) date('Y', strtotime($data['tanggal_surat'])),
            'jenis_surat_id' => $data['jenis_surat_id'],
            'perihal' => $data['perihal'],
            'tujuan_surat' => $data['tujuan_surat'],
            'penyetuju_id' => $data['penyetuju_id'],
            'tampil_pdf_publik' => (bool) $data['tampil_pdf_publik'],
            'keterangan_arsip' => $data['keterangan_arsip'] ?? null,
        ];
    }

    /** Unik terhadap semua surat, termasuk yang dihapus, karena nomor surat tidak boleh dipakai ulang. */
    private function aturanNomor(?SuratKeluar $surat): array
    {
        return ['required', 'string', 'max:100', 'regex:/^[^\x00-\x1F<>]+$/u',
            Rule::unique('surat_keluars', 'nomor_surat_resmi')->ignore($surat?->id)];
    }

    private function pesanNomor(): array
    {
        return [
            'nomor_surat_resmi.regex' => 'Nomor surat memuat karakter yang tidak diizinkan.',
            'nomor_surat_resmi.unique' => 'Nomor tersebut sudah dipakai surat lain (termasuk surat yang dihapus).',
        ];
    }

    private function rapikanNomor(string $nomor): string
    {
        return trim(preg_replace('/\s+/', ' ', $nomor));
    }

    private function label(): array
    {
        return [
            'nomor_surat_resmi' => 'nomor surat', 'tanggal_surat' => 'tanggal surat', 'jenis_surat_id' => 'jenis surat',
            'tujuan_surat' => 'tujuan surat', 'penyetuju_id' => 'penanda tangan', 'berkas' => 'berkas surat',
            'keterangan_arsip' => 'keterangan', 'alasan_koreksi' => 'alasan koreksi',
        ];
    }
}
