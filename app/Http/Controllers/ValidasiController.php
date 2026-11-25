<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Services\SuratRenderer;
use Illuminate\Http\Request;

class ValidasiController extends Controller
{
    /** Halaman publik memuat isi surat, jadi tidak boleh masuk indeks mesin pencari. */
    private const TANPA_INDEKS = ['X-Robots-Tag' => 'noindex, nofollow'];

    public function show(string $uuid)
    {
        $surat = $this->cariTerbit($uuid);

        return response()->view('validasi.show', compact('surat'), $surat ? 200 : 404, self::TANPA_INDEKS);
    }

    public function pdf(Request $request, string $uuid)
    {
        $surat = $this->cariTerbit($uuid);
        abort_unless($surat?->tampilPdfPublik(), 404);

        $response = SuratRenderer::pdf($surat, $request->boolean('unduh'), salinan: true);
        $response->headers->add(self::TANPA_INDEKS);

        return $response;
    }

    /** Draf, surat yang belum disetujui, dan surat terhapus tidak pernah dibuka ke publik. */
    private function cariTerbit(string $uuid): ?SuratKeluar
    {
        return SuratKeluar::with(['penyetuju', 'jenisSurat'])
            ->where('uuid_validasi', $uuid)
            ->where('status', SuratKeluar::TERBIT)
            ->first();
    }
}
