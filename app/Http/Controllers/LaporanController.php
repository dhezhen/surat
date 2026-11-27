<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Rekap tahunan surat keluar dan surat masuk. Pengelompokan dilakukan di PHP agar sama di MySQL
 * dan SQLite; jumlah surat satu prodi per tahun cukup kecil untuk itu.
 */
class LaporanController extends Controller
{
    public const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function __invoke(Request $request)
    {
        $daftarTahun = $this->daftarTahun();
        $tahun = in_array($request->integer('tahun'), $daftarTahun, true) ? $request->integer('tahun') : $daftarTahun[0];

        $keluar = SuratKeluar::with('jenisSurat:id,nama_surat')
            ->where('status', SuratKeluar::TERBIT)
            ->where('tahun_terbit', $tahun)
            ->get(['id', 'jenis_surat_id', 'tanggal_surat', 'diajukan_at', 'disetujui_at']);

        $masuk = SuratMasuk::whereYear('tanggal_diterima', $tahun)->get(['id', 'kategori', 'tanggal_diterima']);

        $perBulanKeluar = $this->perBulan($keluar->map(fn ($s) => $s->tanggal_surat->month));
        $perBulanMasuk = $this->perBulan($masuk->map(fn ($s) => $s->tanggal_diterima->month));

        return view('laporan.index', [
            'tahun' => $tahun,
            'daftarTahun' => $daftarTahun,
            'bulan' => self::BULAN,
            'totalKeluar' => $keluar->count(),
            'totalMasuk' => $masuk->count(),
            'perBulanKeluar' => $perBulanKeluar,
            'perBulanMasuk' => $perBulanMasuk,
            'skala' => $this->skalaAtas(max([...$perBulanKeluar, ...$perBulanMasuk, 0])),
            'perJenis' => $this->rincian($keluar->map(fn ($s) => $s->jenisSurat?->nama_surat ?? 'Tanpa jenis')),
            'perKategori' => $this->rincian($masuk->pluck('kategori')),
            'persetujuan' => $this->lamaPersetujuan($keluar),
        ]);
    }

    /** @return list<int> tahun terbaru lebih dulu, dari arsip tertua hingga tahun berjalan */
    private function daftarTahun(): array
    {
        $sekarang = (int) now()->year;
        $tertua = collect([
            SuratKeluar::where('status', SuratKeluar::TERBIT)->min('tahun_terbit'),
            ($tgl = SuratMasuk::min('tanggal_diterima')) ? (int) substr((string) $tgl, 0, 4) : null,
        ])->filter()->min() ?? $sekarang;

        return range($sekarang, min((int) $tertua, $sekarang));
    }

    /** @return list<int> 12 angka, Januari sampai Desember */
    private function perBulan(Collection $bulan): array
    {
        $hitung = $bulan->countBy();

        return array_map(fn ($b) => (int) ($hitung[$b] ?? 0), range(1, 12));
    }

    /** Batas atas sumbu yang habis dibagi empat agar garis bantu jatuh di bilangan bulat. */
    private function skalaAtas(int $maks): int
    {
        if ($maks <= 4) {
            return 4;
        }
        $langkah = 10 ** floor(log10($maks / 4));
        foreach ([1, 2, 5, 10] as $kali) {
            if ($langkah * $kali * 4 >= $maks) {
                return (int) ($langkah * $kali * 4);
            }
        }

        return (int) ($langkah * 40);
    }

    /** @return list<array{nama: string, jumlah: int, persen: float}> urut dari yang terbanyak */
    private function rincian(Collection $nama): array
    {
        $total = max($nama->count(), 1);

        return $nama->countBy()
            ->sortDesc()
            ->map(fn ($jumlah, $n) => ['nama' => (string) $n, 'jumlah' => $jumlah, 'persen' => round($jumlah / $total * 100, 1)])
            ->values()
            ->all();
    }

    /** @return array{jumlah: int, rata: ?int, median: ?int, tercepat: ?int, terlama: ?int} dalam detik */
    private function lamaPersetujuan(Collection $keluar): array
    {
        $durasi = $keluar
            ->filter(fn ($s) => $s->diajukan_at && $s->disetujui_at && $s->disetujui_at->gte($s->diajukan_at))
            ->map(fn ($s) => (int) $s->diajukan_at->diffInSeconds($s->disetujui_at))
            ->sort()
            ->values();

        return [
            'jumlah' => $durasi->count(),
            'rata' => $durasi->isEmpty() ? null : (int) round($durasi->avg()),
            'median' => $durasi->isEmpty() ? null : (int) round($durasi->median()),
            'tercepat' => $durasi->first(),
            'terlama' => $durasi->last(),
        ];
    }

    public static function formatDurasi(?int $detik): string
    {
        if ($detik === null) {
            return '–';
        }
        if ($detik < 3600) {
            return max(1, (int) round($detik / 60)).' menit';
        }
        if ($detik < 86400) {
            return number_format($detik / 3600, 1, ',', '.').' jam';
        }

        return number_format($detik / 86400, 1, ',', '.').' hari';
    }
}
