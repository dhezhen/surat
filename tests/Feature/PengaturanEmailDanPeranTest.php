<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\Peran;
use App\Models\User;
use App\Support\PengaturanEmail;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanEmailDanPeranTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'super_admin')->first();
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
    }

    private function smtp(array $ubah = []): array
    {
        return $ubah + [
            'aktif' => '1', 'host' => 'smtp.contoh.ac.id', 'port' => 587, 'enkripsi' => 'tls',
            'username' => 'prodi@contoh.ac.id', 'sandi' => 'sandi-rahasia-smtp', 'dari_alamat' => 'prodi@contoh.ac.id', 'dari_nama' => 'Prodi Contoh',
        ];
    }

    private function penggunaBaru(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Pengguna Baru', 'email' => 'baru@uniku.ac.id', 'jabatan' => 'Sekretaris Jurusan',
            'role' => 'staff_prodi', 'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123', 'is_active' => '1',
        ], $ubah);
    }

    public function test_hanya_super_admin_yang_mengatur_email_dan_peran(): void
    {
        $this->actingAs($this->staff)->put(route('pengaturan.aplikasi.email'), $this->smtp())->assertForbidden();
        $this->actingAs($this->kaprodi)->post(route('pengaturan.aplikasi.peran.store'), ['nama_peran' => 'X', 'hak_akses_peran' => 'staff_prodi'])->assertForbidden();
        $this->assertNull(Pengaturan::ambil('email.host'));
        $this->assertSame(0, Peran::count());
    }

    public function test_pengaturan_smtp_disimpan_dan_dipakai_mailer(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), $this->smtp())->assertSessionHasNoErrors();

        $this->assertNotSame('sandi-rahasia-smtp', Pengaturan::ambil('email.sandi'), 'kata sandi disimpan terenkripsi');
        $this->assertSame('sandi-rahasia-smtp', PengaturanEmail::sandi());

        $halaman = $this->actingAs($this->admin)->get(route('pengaturan.aplikasi'))->assertOk();
        $halaman->assertSee('smtp.contoh.ac.id')->assertDontSee('sandi-rahasia-smtp');

        // Mailer yang dibuat baru membaca pengaturan dari database.
        config(['mail.default' => 'array', 'mail.mailers.smtp.host' => '127.0.0.1']);
        app()->forgetInstance('mail.manager');
        app('mail.manager');
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.contoh.ac.id', config('mail.mailers.smtp.host'));
        $this->assertSame('sandi-rahasia-smtp', config('mail.mailers.smtp.password'));
        $this->assertSame('prodi@contoh.ac.id', config('mail.from.address'));
        $this->assertSame('Prodi Contoh', config('mail.from.name'));

        // Kata sandi kosong tidak menimpa yang tersimpan; kotak "hapus" menghapusnya.
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), $this->smtp(['sandi' => '']))->assertSessionHasNoErrors();
        $this->assertSame('sandi-rahasia-smtp', PengaturanEmail::sandi());
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), $this->smtp(['sandi' => '', 'hapus_sandi' => '1']));
        $this->assertNull(PengaturanEmail::sandi());
    }

    public function test_smtp_nonaktif_tidak_mengubah_mailer_env(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), $this->smtp(['aktif' => '0']))->assertSessionHasNoErrors();

        config(['mail.default' => 'array']);
        app()->forgetInstance('mail.manager');
        app('mail.manager');
        $this->assertSame('array', config('mail.default'));
    }

    public function test_validasi_smtp_saat_diaktifkan(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), $this->smtp(['host' => '', 'dari_alamat' => 'bukan-email']))
            ->assertSessionHasErrorsIn('email', ['host', 'dari_alamat']);
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), $this->smtp(['host' => 'http://smtp.contoh.ac.id']))
            ->assertSessionHasErrorsIn('email', ['host']);

        // Boleh dikosongkan selama tidak diaktifkan.
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), ['aktif' => '0', 'enkripsi' => 'tls'])->assertSessionHasNoErrors();
    }

    public function test_uji_kirim_melaporkan_kegagalan(): void
    {
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.email.uji'))->assertSessionHasErrorsIn('email', ['uji']);

        // Port 1 di localhost tidak melayani SMTP, jadi pengiriman pasti gagal dan galatnya ditampilkan.
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.email'), $this->smtp(['aktif' => '0', 'host' => '127.0.0.1', 'port' => 1, 'enkripsi' => 'none']));
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.email.uji'))->assertSessionHasErrorsIn('email', ['uji']);
    }

    public function test_peran_tambahan_memakai_hak_akses_bawaan(): void
    {
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.peran.store'), ['nama_peran' => 'Sekretaris Jurusan', 'hak_akses_peran' => 'staff_prodi'])
            ->assertSessionHasNoErrors();
        $peran = Peran::where('nama', 'Sekretaris Jurusan')->firstOrFail();

        $this->actingAs($this->admin)->get(route('pengguna.create'))->assertSee('Sekretaris Jurusan (akses Sekretaris / Staff Prodi)');
        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->penggunaBaru(['role' => "peran:{$peran->id}"]))
            ->assertRedirect(route('pengguna.index'));

        $baru = User::where('email', 'baru@uniku.ac.id')->firstOrFail();
        $this->assertSame('staff_prodi', $baru->role);
        $this->assertSame($peran->id, $baru->peran_id);
        $this->assertSame('Sekretaris Jurusan', $baru->labelRole());
        $this->assertTrue($baru->isStaff());
        $this->actingAs($baru)->get(route('surat-keluar.create'))->assertOk();
        $this->actingAs($baru)->get(route('pengguna.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('pengguna.index'))->assertSee('Sekretaris Jurusan');

        // Kembali ke peran bawaan melepas peran tambahan.
        $this->actingAs($this->admin)->put(route('pengguna.update', $baru), $this->penggunaBaru(['role' => 'kaprodi', 'is_active' => '0', 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors();
        $baru->refresh();
        $this->assertSame('kaprodi', $baru->role);
        $this->assertNull($baru->peran_id);
    }

    public function test_aturan_satu_kaprodi_aktif_berlaku_untuk_peran_tambahan(): void
    {
        $peran = Peran::create(['nama' => 'Ketua Jurusan', 'hak_akses' => 'kaprodi']);

        $this->actingAs($this->admin)->post(route('pengguna.store'), $this->penggunaBaru(['role' => "peran:{$peran->id}"]))
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'baru@uniku.ac.id']);
    }

    public function test_peran_yang_dipakai_tidak_bisa_dihapus_atau_diganti_hak_aksesnya(): void
    {
        $peran = Peran::create(['nama' => 'Admin Fakultas', 'hak_akses' => 'staff_prodi']);
        $this->staff->update(['peran_id' => $peran->id]);

        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.update', $peran), ['nama' => 'Admin Fakultas', 'hak_akses' => 'super_admin'])
            ->assertSessionHasErrorsIn('peran', ['hak_akses']);
        $this->actingAs($this->admin)->delete(route('pengaturan.aplikasi.peran.destroy', $peran))->assertSessionHasErrorsIn('peran', ['hapus']);
        $this->assertSame('staff_prodi', $peran->fresh()->hak_akses);

        // Nama tetap boleh diganti.
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.update', $peran), ['nama' => 'Admin TU', 'hak_akses' => 'staff_prodi'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Admin TU', $this->staff->fresh()->labelRole());

        $this->staff->update(['peran_id' => null]);
        $this->actingAs($this->admin)->delete(route('pengaturan.aplikasi.peran.destroy', $peran))->assertSessionHasNoErrors();
        $this->assertModelMissing($peran);
    }

    public function test_nama_peran_tidak_boleh_ganda(): void
    {
        Peran::create(['nama' => 'Admin TU', 'hak_akses' => 'staff_prodi']);

        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.peran.store'), ['nama_peran' => 'Admin TU', 'hak_akses_peran' => 'staff_prodi'])
            ->assertSessionHasErrorsIn('peranBaru', ['nama_peran']);
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.peran.store'), ['nama_peran' => 'super admin', 'hak_akses_peran' => 'staff_prodi'])
            ->assertSessionHasErrorsIn('peranBaru', ['nama_peran']);
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.peran.store'), ['nama_peran' => 'Peran Aneh', 'hak_akses_peran' => 'dewa'])
            ->assertSessionHasErrorsIn('peranBaru', ['hak_akses_peran']);
        $this->assertSame(1, Peran::count());
    }

    public function test_peran_inti_bisa_diganti_nama_tanpa_mengubah_kewenangan(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.bawaan', 'staff_prodi'), ['nama' => 'Tenaga Kependidikan'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Tenaga Kependidikan', $this->staff->fresh()->labelRole());
        $this->assertSame('staff_prodi', $this->staff->fresh()->role);
        $this->assertTrue($this->staff->fresh()->isStaff());
        $this->actingAs($this->admin)->get(route('pengguna.create'))->assertSee('Tenaga Kependidikan');
        $this->actingAs($this->admin)->get(route('pengaturan.aplikasi'))->assertSee('Nama asli: Sekretaris / Staff Prodi');

        // Peran tambahan menampilkan nama baru peran inti sebagai hak aksesnya.
        Peran::create(['nama' => 'Admin TU', 'hak_akses' => 'staff_prodi']);
        $this->actingAs($this->admin)->get(route('pengaturan.aplikasi'))->assertSee('Seperti Tenaga Kependidikan');

        // Nama asli atau kosong mengembalikan ke bawaan.
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.bawaan', 'staff_prodi'), ['nama' => 'Sekretaris / Staff Prodi']);
        $this->assertNull(Pengaturan::ambil('peran.nama.staff_prodi'));
        app()->forgetInstance('peran.nama');
        $this->assertSame('Sekretaris / Staff Prodi', $this->staff->fresh()->labelRole());
    }

    public function test_nama_peran_inti_tidak_boleh_bentrok(): void
    {
        Peran::create(['nama' => 'Admin TU', 'hak_akses' => 'staff_prodi']);

        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.bawaan', 'kaprodi'), ['nama' => 'admin tu', 'baris' => 'bawaan:kaprodi'])
            ->assertSessionHasErrorsIn('peran', ['nama']);
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.bawaan', 'kaprodi'), ['nama' => 'Super Admin'])
            ->assertSessionHasErrorsIn('peran', ['nama']);
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.bawaan', 'kaprodi'), ['nama' => ''])
            ->assertSessionHasErrorsIn('peran', ['nama']);
        $this->actingAs($this->admin)->put('/pengaturan/aplikasi/peran-bawaan/dewa', ['nama' => 'X'])->assertNotFound();
        $this->actingAs($this->staff)->put(route('pengaturan.aplikasi.peran.bawaan', 'kaprodi'), ['nama' => 'X'])->assertForbidden();
        $this->assertNull(Pengaturan::ambil('peran.nama.kaprodi'));

        // Nama baru peran inti juga tidak boleh dipakai peran tambahan.
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.peran.bawaan', 'kaprodi'), ['nama' => 'Ketua Jurusan'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.peran.store'), ['nama_peran' => 'Ketua Jurusan', 'hak_akses_peran' => 'staff_prodi'])
            ->assertSessionHasErrorsIn('peranBaru', ['nama_peran']);
    }

    public function test_baris_yang_gagal_disimpan_tetap_terbuka_dengan_pesan(): void
    {
        $peran = Peran::create(['nama' => 'Admin TU', 'hak_akses' => 'staff_prodi']);

        $this->actingAs($this->admin)->from(route('pengaturan.aplikasi'))
            ->put(route('pengaturan.aplikasi.peran.update', $peran), ['nama' => 'Super Admin', 'hak_akses' => 'staff_prodi', 'baris' => "peran:{$peran->id}"]);

        $this->actingAs($this->admin)->get(route('pengaturan.aplikasi'))
            ->assertSee('Nama ini sudah dipakai peran lain.')
            ->assertSee('value="Super Admin"', false);
    }

    public function test_keadaan_kosong_peran_tambahan(): void
    {
        $this->actingAs($this->admin)->get(route('pengaturan.aplikasi'))->assertSee('Belum ada peran tambahan.');
    }

    public function test_halaman_pengaturan_aplikasi_tampil(): void
    {
        Peran::create(['nama' => 'Admin TU', 'hak_akses' => 'staff_prodi']);

        $this->actingAs($this->admin)->get(route('pengaturan.aplikasi'))
            ->assertOk()->assertSee('Email (SMTP)')->assertSee('Peran pengguna')->assertSee('Admin TU');
    }
}
