<?php

namespace App\Http\Controllers;

use App\Models\Peran;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PenggunaController extends Controller
{
    public function index()
    {
        $urutan = [User::ROLE_SUPER_ADMIN => 0, User::ROLE_KAPRODI => 1, User::ROLE_STAFF => 2];

        return view('pengguna.index', [
            'penggunas' => User::with('peran')->orderBy('name')->get()->sortBy(fn ($u) => $urutan[$u->role] ?? 9)->values(),
        ]);
    }

    public function create()
    {
        return view('pengguna.form', ['pengguna' => new User(['role' => User::ROLE_STAFF, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $this->pastikanSatuKaprodiAktif($data['role'], $data['is_active']);

        User::create($data);

        return redirect()->route('pengguna.index')->with('status', 'Pengguna ditambahkan.');
    }

    public function edit(User $pengguna)
    {
        return view('pengguna.form', compact('pengguna'));
    }

    public function update(Request $request, User $pengguna): RedirectResponse
    {
        $data = $this->validasi($request, $pengguna);

        if ($pengguna->is($request->user())) {
            // Mengubah peran atau menonaktifkan diri sendiri bisa mengunci Super Admin dari sistem.
            $data['role'] = $pengguna->role;
            $data['peran_id'] = $pengguna->peran_id;
            $data['is_active'] = true;
        }

        $this->pastikanSatuKaprodiAktif($data['role'], $data['is_active'], $pengguna);
        $this->pastikanMasihAdaSuperAdmin($pengguna, $data['role'], $data['is_active']);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $pengguna->update($data);

        return redirect()->route('pengguna.index')->with('status', 'Data pengguna diperbarui.');
    }

    public function status(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->is($request->user())) {
            return back()->withErrors(['status' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);
        }

        $aktif = ! $pengguna->is_active;
        $this->pastikanSatuKaprodiAktif($pengguna->role, $aktif, $pengguna);
        $this->pastikanMasihAdaSuperAdmin($pengguna, $pengguna->role, $aktif);

        $pengguna->update(['is_active' => $aktif]);

        return back()->with('status', $pengguna->name.($aktif ? ' diaktifkan.' : ' dinonaktifkan.'));
    }

    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->is($request->user())) {
            return back()->withErrors(['hapus' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }
        if ($pengguna->punyaRiwayatSurat()) {
            return back()->withErrors(['hapus' => $pengguna->name.' terkait dengan surat yang sudah dibuat atau disetujui. Nonaktifkan akunnya, jangan dihapus.']);
        }
        $this->pastikanMasihAdaSuperAdmin($pengguna, null, false);

        $pengguna->delete();

        return redirect()->route('pengguna.index')->with('status', 'Pengguna dihapus.');
    }

    private function validasi(Request $request, ?User $pengguna = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($pengguna)],
            'jabatan' => ['required', 'string', 'max:255'],
            'nidn' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys(User::pilihanPeran()))],
            'password' => [$pengguna ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], [
            'email.unique' => 'Email ini sudah dipakai pengguna lain.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ], [
            'name' => 'Nama', 'jabatan' => 'Jabatan', 'nidn' => \App\Support\Identitas::ambil('label_id_penandatangan'), 'role' => 'Peran',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // Peran tambahan disimpan sebagai peran_id, sedangkan kolom role memuat hak akses bawaannya.
        if (str_starts_with($data['role'], 'peran:')) {
            $peran = Peran::findOrFail((int) substr($data['role'], 6));
            $data['role'] = $peran->hak_akses;
            $data['peran_id'] = $peran->id;
        } else {
            $data['peran_id'] = null;
        }

        return $data;
    }

    /** Penandatangan surat diambil dari Kaprodi aktif, jadi tidak boleh ada dua sekaligus. */
    private function pastikanSatuKaprodiAktif(string $role, bool $aktif, ?User $kecuali = null): void
    {
        if ($role !== User::ROLE_KAPRODI || ! $aktif) {
            return;
        }

        $lain = User::where('role', User::ROLE_KAPRODI)->aktif()->when($kecuali, fn ($q) => $q->where('id', '!=', $kecuali->id))->first();
        if ($lain) {
            throw ValidationException::withMessages([
                'role' => "Sudah ada Ketua Prodi aktif ({$lain->name}). Nonaktifkan atau ubah peran akun tersebut terlebih dahulu.",
            ]);
        }
    }

    private function pastikanMasihAdaSuperAdmin(User $pengguna, ?string $roleBaru, bool $aktifBaru): void
    {
        $tetapSuperAdmin = $roleBaru === User::ROLE_SUPER_ADMIN && $aktifBaru;
        if ($pengguna->isSuperAdmin() && $pengguna->is_active && ! $tetapSuperAdmin) {
            $sisa = User::where('role', User::ROLE_SUPER_ADMIN)->aktif()->where('id', '!=', $pengguna->id)->count();
            if ($sisa === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Harus tetap ada minimal satu Super Admin aktif.',
                ]);
            }
        }
    }
}
