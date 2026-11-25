<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EksporController extends Controller
{
    public function suratKeluar(Request $request)
    {
        $tahun = $request->integer('tahun') ?: null;

        return $this->csv('buku-agenda-surat-keluar', [
            'No. Urut', 'Nomor Surat', 'Tanggal Surat', 'Jenis Surat', 'Tujuan', 'Perihal', 'Penandatangan', 'Pembuat',
        ], function ($out) use ($tahun) {
            SuratKeluar::with(['jenisSurat', 'penyetuju', 'pembuat'])
                ->where('status', SuratKeluar::TERBIT)
                ->when($tahun, fn ($q) => $q->where('tahun_terbit', $tahun))
                ->orderBy('tahun_terbit')->orderBy('nomor_urut')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $s) {
                        fputcsv($out, array_map([$this, 'aman'], [
                            $s->nomor_urut, $s->nomor_surat_resmi, $s->tanggal_surat->format('Y-m-d'),
                            $s->jenisSurat->nama_surat, $s->tujuan_surat, $s->perihal,
                            $s->penyetuju?->name, $s->pembuat?->name,
                        ]));
                    }
                });
        }, $tahun);
    }

    public function suratMasuk(Request $request)
    {
        $tahun = $request->integer('tahun') ?: null;

        return $this->csv('buku-agenda-surat-masuk', [
            'Nomor Agenda', 'Nomor Surat Asal', 'Tanggal Surat', 'Tanggal Diterima', 'Pengirim', 'Perihal', 'Kategori', 'Keterangan',
        ], function ($out) use ($tahun) {
            SuratMasuk::when($tahun, fn ($q) => $q->whereYear('tanggal_diterima', $tahun))
                ->orderBy('nomor_agenda')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $s) {
                        fputcsv($out, array_map([$this, 'aman'], [
                            $s->nomor_agenda, $s->nomor_surat_asal, $s->tanggal_surat->format('Y-m-d'),
                            $s->tanggal_diterima->format('Y-m-d'), $s->pengirim, $s->perihal, $s->kategori, $s->keterangan,
                        ]));
                    }
                });
        }, $tahun);
    }

    private function csv(string $nama, array $header, \Closure $isi, ?int $tahun): StreamedResponse
    {
        $file = $nama.($tahun ? "-{$tahun}" : '').'.csv';

        return response()->streamDownload(function () use ($header, $isi) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $header);
            $isi($out);
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Mencegah injeksi rumus saat CSV dibuka di Excel. */
    private function aman(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }
}
