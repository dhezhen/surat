<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HapusSuratTest extends TestCase
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

    private function surat(string $status): SuratKeluar
    {
        return SuratKeluar::create([
            'jenis_surat_id' => JenisSurat::where('nama_surat', 'Surat Baru (Isi Saja)')->first()->id,
            'pembuat_id' => $this->staff->id, 'tanggal_surat' => now(), 'tujuan_surat' => 'Dekan',
            'perihal' => 'Uji hapus '.$status, 'data_dinamis' => ['isi_surat' => '<p>x</p>'], 'status' => $status,
        ]);
    }

    private function terbitkan(SuratKeluar $s): SuratKeluar
    {
        $s->update(['status' => 'menunggu_approval']);
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s));

        return $s->fresh();
    }

    public function test_staff_boleh_menghapus_draf_dan_revisi_saja(): void
    {
        foreach (['draft', 'revisi'] as $status) {
            $s = $this->surat($status);
            $this->actingAs($this->staff)->delete(route('surat-keluar.destroy', $s))->assertRedirect(route('surat-keluar.index'));
            $this->assertSoftDeleted($s);
        }

        foreach (['menunggu_approval', 'terbit'] as $status) {
            $s = $this->surat($status);
            $this->actingAs($this->staff)->delete(route('surat-keluar.destroy', $s))->assertForbidden();
            $this->assertNotSoftDeleted($s);
        }
    }

    public function test_kaprodi_tidak_boleh_menghapus_dan_tamu_dialihkan(): void
    {
        $s = $this->surat('draft');
        $this->actingAs($this->kaprodi)->delete(route('surat-keluar.destroy', $s))->assertForbidden();

        auth()->logout();
        $this->delete(route('surat-keluar.destroy', $s))->assertRedirect('/login');
        $this->assertNotSoftDeleted($s);
    }

    public function test_surat_terhapus_tidak_tampil_dan_halamannya_404(): void
    {
        $s = $this->surat('draft');
        $this->actingAs($this->staff)->delete(route('surat-keluar.destroy', $s));

        $this->actingAs($this->staff)->get(route('surat-keluar.index'))->assertDontSee('Uji hapus draft');
        $this->actingAs($this->staff)->get(route('surat-keluar.show', $s))->assertNotFound();
        $this->actingAs($this->staff)->get(route('pencarian', ['q' => 'Uji hapus']))->assertDontSee('Uji hapus draft');
    }

    public function test_super_admin_menghapus_surat_terbit_nomor_tidak_dipakai_ulang_dan_qr_tidak_valid(): void
    {
        $terbit = $this->terbitkan($this->surat('draft'));
        $this->assertSame(1, $terbit->nomor_urut);
        $uuid = $terbit->uuid_validasi;

        auth()->logout();
        $this->get(route('validasi', $uuid))->assertOk()->assertSee('DOKUMEN SAH');

        $this->actingAs($this->admin)->delete(route('surat-keluar.destroy', $terbit))->assertRedirect(route('surat-keluar.index'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'tidak dipakai ulang'));
        $this->assertSoftDeleted($terbit);

        auth()->logout();
        $this->get(route('validasi', $uuid))->assertNotFound()->assertSee('TIDAK DITEMUKAN');

        // Surat berikutnya mendapat nomor 2, bukan 1 lagi.
        $berikut = $this->terbitkan($this->surat('draft'));
        $this->assertSame(2, $berikut->nomor_urut);
        $this->actingAs($this->staff)->get(route('surat-keluar.create'))->assertSee('/', false); // halaman tetap render
        $this->assertSame(3, (int) substr(\App\Services\PenomoranService::perkiraan(), 0, strpos(\App\Services\PenomoranService::perkiraan(), '/')));
    }

    public function test_super_admin_dapat_memulihkan_surat(): void
    {
        $terbit = $this->terbitkan($this->surat('draft'));
        $this->actingAs($this->admin)->delete(route('surat-keluar.destroy', $terbit));

        $this->actingAs($this->admin)->get(route('surat-keluar.terhapus'))->assertOk()->assertSee('Uji hapus')->assertSee($terbit->nomor_surat_resmi);

        $this->actingAs($this->admin)->post(route('surat-keluar.pulihkan', $terbit))->assertRedirect(route('surat-keluar.show', $terbit));
        $this->assertNotSoftDeleted($terbit);

        auth()->logout();
        $this->get(route('validasi', $terbit->uuid_validasi))->assertOk()->assertSee('DOKUMEN SAH');
    }

    public function test_daftar_terhapus_dan_pemulihan_hanya_untuk_super_admin(): void
    {
        $s = $this->surat('draft');
        $this->actingAs($this->staff)->delete(route('surat-keluar.destroy', $s));

        $this->actingAs($this->staff)->get(route('surat-keluar.terhapus'))->assertForbidden();
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.terhapus'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('surat-keluar.pulihkan', $s))->assertForbidden();
        $this->assertSoftDeleted($s);
    }

    public function test_tombol_hapus_tampil_sesuai_peran_dan_status(): void
    {
        $draf = $this->surat('draft');
        $terbit = $this->terbitkan($this->surat('draft'));

        $this->actingAs($this->staff)->get(route('surat-keluar.show', $draf))->assertSee('Hapus surat');
        $this->actingAs($this->staff)->get(route('surat-keluar.show', $terbit))->assertDontSee('Hapus surat');
        $this->actingAs($this->admin)->get(route('surat-keluar.show', $terbit))->assertSee('Hapus surat');
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.show', $terbit))->assertDontSee('Hapus surat');
    }

    public function test_pengguna_dengan_surat_terhapus_tetap_tidak_bisa_dihapus(): void
    {
        $s = $this->surat('draft');
        $this->actingAs($this->staff)->delete(route('surat-keluar.destroy', $s));

        $this->actingAs($this->admin)->delete(route('pengguna.destroy', $this->staff))->assertSessionHasErrors('hapus');
        $this->assertDatabaseHas('users', ['id' => $this->staff->id]);
    }
}
