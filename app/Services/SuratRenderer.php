<?php

namespace App\Services;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SuratRenderer
{
    /** Data yang dipakai bersama oleh tampilan kertas (layar) dan PDF. */
    public static function kertas(SuratKeluar $surat): array
    {
        if (! $surat->isTerbit()) {
            return self::bagian($surat) + ['penandatangan' => self::penandatangan($surat), 'ttd' => null, 'qr' => null, 'kop' => null];
        }

        $arsip = ArsipSurat::ambil($surat);
        $penandatangan = $arsip['penandatangan'] ? new User($arsip['penandatangan']) : null;

        // QR tidak ikut dibekukan: alamatnya mengikuti domain yang sedang dipakai.
        $qr = null;
        if ($surat->metodeTtd($penandatangan) !== SuratKeluar::TTD_BASAH) {
            $svg = QrCode::format('svg')->size(90)->margin(0)->generate(route('validasi', $surat->uuid_validasi));
            $qr = 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        return ['penandatangan' => $penandatangan, 'qr' => $qr] + $arsip;
    }

    /** PDF surat terbit. Salinan untuk halaman publik diberi watermark agar tidak dipakai sebagai surat asli. */
    public static function pdf(SuratKeluar $surat, bool $unduh, bool $salinan = false): Response
    {
        $surat->loadMissing(['jenisSurat', 'penyetuju']);
        $kertas = self::kertas($surat);
        if ($salinan && $kertas['ttd']) {
            $kertas['ttd'] = self::capSalinan($kertas['ttd']);
        }
        $pdf = Pdf::loadView('pdf.surat', ['surat' => $surat, 'pdf' => true, 'salinan' => $salinan] + $kertas)->setPaper('a4');
        $nama = ($salinan ? 'Salinan-' : 'Surat-').str_replace(['/', '\\'], '-', $surat->nomor_surat_resmi).'.pdf';

        return $unduh ? $pdf->download($nama) : $pdf->stream($nama);
    }

    /**
     * Watermark halaman tidak cukup karena gambar tanda tangan bisa diekstrak utuh dari berkas PDF,
     * jadi capnya digambar langsung pada piksel. Gambar yang tidak terbaca tidak disertakan.
     */
    public static function capSalinan(string $dataUri): ?string
    {
        $gambar = @imagecreatefromstring((string) base64_decode(substr($dataUri, strpos($dataUri, ',') + 1)));
        if (! $gambar) {
            return null;
        }

        $teks = 'SALINAN VERIFIKASI';
        $cap = imagecreatetruecolor(imagefontwidth(5) * strlen($teks), imagefontheight(5));
        imagealphablending($cap, false);
        imagefill($cap, 0, 0, imagecolorallocatealpha($cap, 255, 255, 255, 127));
        imagestring($cap, 5, 0, 0, $teks, imagecolorallocatealpha($cap, 185, 28, 28, 20));

        imagealphablending($gambar, true);
        imagesavealpha($gambar, true);
        [$lebar, $tinggi] = [imagesx($gambar), imagesy($gambar)];
        foreach ([0.15, 0.6] as $posisi) {
            imagecopyresampled($gambar, $cap, 0, (int) ($tinggi * $posisi), 0, 0, $lebar, max(1, (int) ($tinggi * 0.25)), imagesx($cap), imagesy($cap));
        }

        ob_start();
        imagepng($gambar);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    /** Nomor untuk surat yang belum terbit, diberi tanda agar tidak dikira nomor resmi. */
    public static function nomorPerkiraan(): string
    {
        return '<span title="Nomor final ditetapkan saat disetujui Ketua Prodi">'
            .e(PenomoranService::perkiraan())
            .' <em style="color:#92400e">(perkiraan)</em></span>';
    }

    public static function tanggal(null|string|\DateTimeInterface $tgl): string
    {
        return $tgl ? Carbon::parse($tgl)->locale('id')->translatedFormat('j F Y') : '';
    }

    /** Peta placeholder => nilai (sudah di-escape HTML) untuk sebuah surat. */
    public static function variabel(SuratKeluar $surat): array
    {
        $vars = [
            'nomor_surat' => $surat->nomor_surat_resmi
                ? e($surat->nomor_surat_resmi)
                : self::nomorPerkiraan(),
            'tanggal_surat' => e(self::tanggal($surat->tanggal_surat)),
            'tujuan_surat' => self::tujuan($surat->tujuan_surat),
            'perihal' => e($surat->perihal),
        ];

        foreach (($surat->jenisSurat->form_schema ?? []) as $field) {
            $name = $field['name'] ?? null;
            if (! $name) {
                continue;
            }
            $val = $surat->data_dinamis[$name] ?? '';
            $type = $field['type'] ?? 'text';
            if ($type === 'date') {
                // {nama}_lengkap menyertakan nama hari, mis. "Kamis, 30 Juli 2026".
                $vars[$name.'_lengkap'] = $val ? e(Carbon::parse($val)->locale('id')->translatedFormat('l, j F Y')) : '';
                $val = self::tanggal($val ?: null);
            }
            $vars[$name] = match ($type) {
                'textarea' => nl2br(e($val)),
                'list' => self::daftar((string) $val),
                'richtext' => HtmlSanitizer::bersihkan((string) $val),
                'tabel' => self::tabel(is_array($val) ? $val : []),
                default => e($val),
            };
        }

        return $vars;
    }

    public static function isi(SuratKeluar $surat): string
    {
        return self::bagian($surat)['isi'];
    }

    public static function lampiran(SuratKeluar $surat): string
    {
        return self::bagian($surat)['lampiran'];
    }

    /**
     * Template dibagi dua pada penanda {{halaman_baru}}: bagian pertama menjadi isi surat, bagian sesudahnya
     * menjadi halaman lampiran yang dicetak setelah tanda tangan. Lampiran kosong dibuang.
     */
    public static function bagian(SuratKeluar $surat): array
    {
        $vars = self::variabel($surat);
        $potongan = preg_split('/\{\{\s*halaman_baru\s*\}\}/', $surat->jenisSurat->template_html, 2);

        $isi = self::isiTemplate($potongan[0], $vars);
        $lampiran = isset($potongan[1]) ? self::isiTemplate($potongan[1], $vars) : '';
        if (trim(strip_tags($lampiran)) === '' && ! str_contains($lampiran, '<table')) {
            $lampiran = '';
        }

        return ['isi' => $isi, 'lampiran' => $lampiran];
    }

    private static function isiTemplate(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', fn ($m) => $vars[$m[1]] ?? '', $template);
    }

    /** Tujuan surat: baris pertama tebal (jabatan/instansi), baris berikutnya biasa, seperti surat dinas prodi. */
    private static function tujuan(?string $teks): string
    {
        $baris = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $teks)), fn ($b) => $b !== ''));
        if (! $baris) {
            return '';
        }

        return '<strong>'.e($baris[0]).'</strong>'
            .implode('', array_map(fn ($b) => '<br>'.e($b), array_slice($baris, 1)));
    }

    /** @param array{kolom?: array, baris?: array} $nilai */
    public static function tabel(array $nilai): string
    {
        $kolom = array_values($nilai['kolom'] ?? []);
        $baris = array_values($nilai['baris'] ?? []);
        if (! $kolom || ! $baris) {
            return '';
        }

        $sel = 'border:1px solid #000; padding:3px 6px; vertical-align:top;';
        $html = '<table style="width:100%; border-collapse:collapse; margin-bottom:8px"><thead><tr>'
            .'<th style="'.$sel.' width:1.2cm; text-align:center; background:#e5e7eb">No</th>';
        foreach ($kolom as $k) {
            $html .= '<th style="'.$sel.' text-align:left; background:#e5e7eb">'.e($k).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($baris as $i => $b) {
            $html .= '<tr><td style="'.$sel.' text-align:center">'.($i + 1).'</td>';
            foreach ($kolom as $j => $_) {
                $html .= '<td style="'.$sel.'">'.e($b[$j] ?? '').'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    /** Satu baris isian menjadi satu butir daftar bernomor. */
    private static function daftar(string $teks): string
    {
        $baris = array_filter(array_map('trim', preg_split('/\R/', $teks)), fn ($b) => $b !== '');
        if (! $baris) {
            return '';
        }

        return '<ol style="margin:0 0 8px 0; padding-left:1.2cm">'
            .implode('', array_map(fn ($b) => '<li>'.e($b).'</li>', $baris)).'</ol>';
    }

    public static function penandatangan(?SuratKeluar $surat = null): ?User
    {
        return $surat?->penyetuju ?? User::where('role', User::ROLE_KAPRODI)->aktif()->first();
    }
}
