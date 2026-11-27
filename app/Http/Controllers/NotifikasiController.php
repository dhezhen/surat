<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        return view('notifikasi.index', [
            'notifikasi' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Menandai dibaca lalu membuka surat terkait. */
    public function buka(Request $request, string $id): RedirectResponse
    {
        $notifikasi = $request->user()->notifications()->findOrFail($id);
        $notifikasi->markAsRead();

        $suratId = $notifikasi->data['surat_id'] ?? null;

        return $suratId
            ? redirect()->route('surat-keluar.show', $suratId)
            : redirect()->route('notifikasi.index');
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
