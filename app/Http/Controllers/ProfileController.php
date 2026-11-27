<?php

namespace App\Http\Controllers;

use App\Services\TandaTanganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'jabatan' => ['required', 'string', 'max:255'],
            'nidn' => ['nullable', 'string', 'max:30'],
        ], [], ['jabatan' => 'Jabatan', 'nidn' => \App\Support\Identitas::ambil('label_id_penandatangan')]);
        $data['notif_email'] = $request->boolean('notif_email');

        $user->update($data);

        return back()->with('status', 'Profil diperbarui.');
    }

    /** Tanda tangan manual (digambar di layar atau diunggah) yang dibubuhkan pada surat terbit. Khusus Kaprodi. */
    public function tandaTangan(Request $request, TandaTanganService $service): RedirectResponse
    {
        abort_unless($request->user()->isKaprodi(), 403);

        $request->validate([
            'tanda_tangan' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:4096'],
            'tanda_tangan_gambar' => ['nullable', 'string', 'max:3000000'],
        ]);

        if ($request->hasFile('tanda_tangan')) {
            $biner = $request->file('tanda_tangan')->get();
        } elseif (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', (string) $request->tanda_tangan_gambar, $m)) {
            $biner = base64_decode($m[1], true) ?: '';
        } else {
            return back()->withErrors(['tanda_tangan' => 'Gambar tanda tangan di kolom atau unggah berkas terlebih dahulu.']);
        }

        try {
            $service->simpan($request->user(), $biner);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['tanda_tangan' => $e->getMessage()]);
        }

        return back()->with('status', 'Tanda tangan disimpan dan akan tampil pada surat yang terbit.');
    }

    public function hapusTandaTangan(Request $request, TandaTanganService $service): RedirectResponse
    {
        abort_unless($request->user()->isKaprodi(), 403);
        $service->hapus($request->user());

        return back()->with('status', 'Tanda tangan dihapus.');
    }
}
