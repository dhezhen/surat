<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $awal = now()->startOfMonth()->toDateString();
        $akhir = now()->endOfMonth()->toDateString();

        return view('dashboard', [
            'suratKeluarBulanIni' => SuratKeluar::where('status', SuratKeluar::TERBIT)->whereBetween('tanggal_surat', [$awal, $akhir])->count(),
            'suratMasukBulanIni' => SuratMasuk::whereBetween('tanggal_diterima', [$awal, $akhir])->count(),
            'menunggu' => SuratKeluar::with(['jenisSurat', 'pembuat'])->where('status', SuratKeluar::MENUNGGU)->oldest('updated_at')->get(),
            'perluRevisi' => $user->isStaff()
                ? SuratKeluar::with('jenisSurat')->where('status', SuratKeluar::REVISI)->latest('updated_at')->get()
                : collect(),
            'terbaru' => SuratKeluar::with('jenisSurat')->visibleTo($user)->latest('updated_at')->limit(6)->get(),
        ]);
    }
}
