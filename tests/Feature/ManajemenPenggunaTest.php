<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManajemenPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'ti@uniku.ac.id')->first();
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
    }

    private function dataBaru(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Dosen Baru, M.Kom', 'email' => 'baru@uniku.ac.id', 'jabatan' => 'Staff Administrasi',
            'role' => 'staff_prodi', 'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123', 'is_active' => '1',
        ], $ubah);
    }

    public function test_ti_uniku_adalah_super_admin_aktif(): void
    {
        $this->assertSame('super_admin', $this->admin->role);
        $this->assertTrue($this->admin->is_active);
        $this->assertTrue($this->admin->isSuperAdmin());
    }

    public function test_seeder_ulang_tidak_menimpa_kata_sandi_super_admin(): void
    {
        $this->admin->update(['password' => 'sandi-pilihan-saya']);
        $hash = $this->admin->fresh()->password;

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->assertSame(1, User::where('email', 'ti@uniku.ac.id')->count());
    }

    public function test_hanya_super_admin_yang_boleh_membuka_manajemen_pengguna(): void
    {
        $this->actingAs($this->staff)->get(route('pengguna.index'))->assertForbidden();
        $this->actingAs($this->kaprodi)->get(route('pengguna.index'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('pengguna.store'), $this->dataBaru())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'baru@uniku.ac.id']);

        foreach ([route('pengguna.index'), route('pengguna.create'), route('pengguna.edit', $this->staff), route('dashboard'), route('surat-keluar.index'), route('surat-masuk.index'), route('pencarian')] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_super_admin_tidak_menjalankan_tugas_staff_atau_kaprodi(): void
    {
        $this->actingAs($this->admin)->get(route('surat-keluar.create'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('jenis-surat.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('surat-masuk.create'))->assertForbidden();

        $surat = SuratKeluar::create([
            'jenis_surat_id' => JenisSurat::first()->id, 'pembuat_id' => $this->staff->id, 'tanggal_surat' => now(),
            'tujuan_surat' => 'X', 'perihal' => 'Y', 'data_dinamis' => [], 'status' => 'menunggu_approval',
        ]);
        $this->actingAs($this->admin)->post(route('surat-keluar.setujui', $surat))->assertForbidden();
        $this->actingAs($this->admin)->get(route('surat-keluar.show', $surat))->assertOk();
    }

    public function test_super_admin_menambah_dan_mengubah_pengguna(): void
    {
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->dataBaru())->assertRedirect(route('pengguna.index'));
        $baru = User::where('email', 'baru@uniku.ac.id')->first();
        $this->assertSame('staff_prodi', $baru->role);

        auth()->logout();
        $this->post('/login', ['email' => 'baru@uniku.ac.id', 'password' => 'rahasia-123'])->assertRedirect(route('dashboard'));
        auth()->logout();

        $this->actingAs($this->admin)->put(route('pengguna.update', $baru), $this->dataBaru(['name' => 'Nama Diganti', 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors();
        $baru->refresh();
        $this->assertSame('Nama Diganti', $baru->name);
        $this->assertTrue(\Hash::check('rahasia-123', $baru->password), 'kata sandi tidak berubah bila dikosongkan');

        $this->actingAs($this->admin)->put(route('pengguna.update', $baru), $this->dataBaru(['password' => 'sandi-baru-456', 'password_confirmation' => 'sandi-baru-456']));
        $this->assertTrue(\Hash::check('sandi-baru-456', $baru->fresh()->password));
    }

    public function test_validasi_email_ganda_dan_kata_sandi(): void
    {
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->dataBaru(['email' => 'staff@prodi.ac.id']))->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->dataBaru(['password' => 'pendek', 'password_confirmation' => 'pendek']))->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->dataBaru(['password_confirmation' => 'beda']))->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->dataBaru(['role' => 'hacker']))->assertSessionHasErrors('role');
    }

    public function test_hanya_satu_ketua_prodi_aktif(): void
    {
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->dataBaru(['role' => 'kaprodi']))->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'baru@uniku.ac.id']);

        // Setelah Kaprodi lama dinonaktifkan, Kaprodi baru boleh dibuat dan menjadi penandatangan.
        $this->actingAs($this->admin)->post(route('pengguna.status', $this->kaprodi))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->dataBaru(['role' => 'kaprodi']))->assertSessionHasNoErrors();
        $this->assertSame('baru@uniku.ac.id', \App\Services\SuratRenderer::penandatangan()->email);

        // Mengaktifkan kembali Kaprodi lama ditolak selama yang baru aktif.
        $this->actingAs($this->admin)->post(route('pengguna.status', $this->kaprodi))->assertSessionHasErrors('role');
    }

    public function test_akun_nonaktif_tidak_bisa_masuk_dan_sesi_aktifnya_diputus(): void
    {
        $this->actingAs($this->admin)->post(route('pengguna.status', $this->staff))->assertSessionHasNoErrors();
        $this->assertFalse($this->staff->fresh()->is_active);

        auth()->logout();
        $this->post('/login', ['email' => 'staff@prodi.ac.id', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Sesi yang sudah berjalan juga ditutup pada permintaan berikutnya.
        $this->actingAs($this->staff->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pengaman_diri_sendiri_dan_super_admin_terakhir(): void
    {
        $this->actingAs($this->admin)->post(route('pengguna.status', $this->admin))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->delete(route('pengguna.destroy', $this->admin))->assertSessionHasErrors('hapus');

        // Mengubah peran lewat form untuk akun sendiri diabaikan.
        $this->actingAs($this->admin)->put(route('pengguna.update', $this->admin), $this->dataBaru(['email' => 'ti@uniku.ac.id', 'role' => 'staff_prodi', 'password' => '', 'password_confirmation' => '', 'is_active' => null]));
        $this->assertSame('super_admin', $this->admin->fresh()->role);
        $this->assertTrue($this->admin->fresh()->is_active);

        // Super Admin lain boleh diturunkan oleh Super Admin yang masih aktif.
        $kedua = User::create(['name' => 'Admin Dua', 'email' => 'dua@uniku.ac.id', 'jabatan' => 'Admin', 'role' => 'super_admin', 'password' => 'rahasia-123']);
        $this->actingAs($this->admin)->put(route('pengguna.update', $kedua), $this->dataBaru(['email' => 'dua@uniku.ac.id', 'role' => 'staff_prodi']))->assertSessionHasNoErrors();
        $this->assertSame('staff_prodi', $kedua->fresh()->role);
        $this->assertSame('super_admin', $this->admin->fresh()->role, 'tetap ada Super Admin aktif');
    }

    public function test_hapus_pengguna_dilarang_bila_punya_riwayat_surat(): void
    {
        SuratKeluar::create([
            'jenis_surat_id' => JenisSurat::first()->id, 'pembuat_id' => $this->staff->id, 'tanggal_surat' => now(),
            'tujuan_surat' => 'X', 'perihal' => 'Y', 'data_dinamis' => [], 'status' => 'draft',
        ]);
        $this->actingAs($this->admin)->delete(route('pengguna.destroy', $this->staff))->assertSessionHasErrors('hapus');
        $this->assertDatabaseHas('users', ['id' => $this->staff->id]);

        $bersih = User::create(['name' => 'Tanpa Surat', 'email' => 'bersih@uniku.ac.id', 'jabatan' => 'Staff', 'role' => 'staff_prodi', 'password' => 'rahasia-123']);
        $this->actingAs($this->admin)->delete(route('pengguna.destroy', $bersih))->assertRedirect(route('pengguna.index'));
        $this->assertDatabaseMissing('users', ['id' => $bersih->id]);
    }
}
