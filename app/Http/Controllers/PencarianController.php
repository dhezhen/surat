<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Http\Request;

class PencarianController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->q);
        $tahun = $request->integer('tahun') ?: null;
        $cari = $q !== '' || $tahun;

        $keluar = collect();
        $masuk = collect();

        if ($cari) {
            $keluar = SuratKeluar::with('jenisSurat')
                ->visibleTo($request->user())
                ->cari($q)
                ->when($tahun, fn ($w) => $w->whereYear('tanggal_surat', $tahun))
                ->latest('tanggal_surat')->limit(50)->get();

            $masuk = SuratMasuk::cari($q)
                ->when($tahun, fn ($w) => $w->whereYear('tanggal_surat', $tahun))
                ->latest('tanggal_surat')->limit(50)->get();
        }

        return view('pencarian.index', compact('q', 'tahun', 'cari', 'keluar', 'masuk'));
    }
}
