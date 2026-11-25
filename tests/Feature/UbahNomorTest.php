<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UbahNomorTest extends TestCase
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

    private function surat(string $status = 'menunggu_approval'): SuratKeluar
    {
        return SuratKeluar::create([
            'jenis_surat_id' => JenisSurat::where('nama_surat', 'Surat Baru (Isi Saja)')->first()->id,
            'pembuat_id' => $this->staff->id, 'tanggal_surat' => now(), 'tujuan_surat' => 'Dekan',
            'perihal' => 'Uji nomor', 'data_dinamis' => ['isi_surat' => '<p>x</p>'], 'status' => $status,
        ]);
    }

    private function terbit(): SuratKeluar
    {
        $s = $this->surat();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s));

        return $s->fresh();
    }

    public function test_staff_dan_super_admin_mengubah_nomor_terbit_dan_pdf_memakainya(): void
    {
        $s = $this->terbit();
        $urut = $s->nomor_urut;

        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $s), ['nomor_surat_resmi' => '  12.A/TI-S1/FKOM-UNIKU/XII/2026 '])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('12.A/TI-S1/FKOM-UNIKU/XII/2026', $s->nomor_surat_resmi);
        $this->assertSame($urut, $s->nomor_urut, 'nomor urut internal tidak berubah');
        $this->actingAs($this->staff)->get(route('surat-keluar.show', $s))->assertSee('12.A/TI-S1/FKOM-UNIKU/XII/2026');
        $this->get(route('validasi', $s->uuid_validasi))->assertSee('12.A/TI-S1/FKOM-UNIKU/XII/2026');

        $this->actingAs($this->admin)->put(route('surat-keluar.nomor', $s), ['nomor_surat_resmi' => 'B-7/2026'])->assertSessionHasNoErrors();
        $this->assertSame('B-7/2026', $s->fresh()->nomor_surat_resmi);
    }

    public function test_nomor_berikutnya_tetap_berlanjut_setelah_nomor_diubah(): void
    {
        $a = $this->terbit();
        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $a), ['nomor_surat_resmi' => 'KHUSUS/1']);
        $b = $this->terbit();

        $this->assertSame($a->nomor_urut + 1, $b->nomor_urut);
    }

    public function test_nomor_tidak_boleh_ganda_kosong_atau_berbahaya(): void
    {
        $a = $this->terbit();
        $b = $this->terbit();

        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $b), ['nomor_surat_resmi' => $a->nomor_surat_resmi])->assertSessionHasErrors('nomor_surat_resmi');
        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $b), ['nomor_surat_resmi' => ''])->assertSessionHasErrors('nomor_surat_resmi');
        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $b), ['nomor_surat_resmi' => '<script>x</script>'])->assertSessionHasErrors('nomor_surat_resmi');

        $a->delete();
        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $b), ['nomor_surat_resmi' => $a->nomor_surat_resmi])->assertSessionHasErrors('nomor_surat_resmi');
        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $b), ['nomor_surat_resmi' => $b->nomor_surat_resmi])->assertSessionHasNoErrors();
    }

    public function test_hanya_surat_terbit_dan_hanya_staff_atau_super_admin(): void
    {
        $draf = $this->surat('draft');
        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $draf), ['nomor_surat_resmi' => 'X/1'])->assertForbidden();

        $s = $this->terbit();
        $this->actingAs($this->kaprodi)->put(route('surat-keluar.nomor', $s), ['nomor_surat_resmi' => 'X/1'])->assertForbidden();
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.show', $s))->assertDontSee('Ubah nomor');
        $this->actingAs($this->staff)->get(route('surat-keluar.show', $s))->assertSee('Ubah nomor');
    }

    public function test_tombol_hapus_di_daftar_sesuai_hak_akses(): void
    {
        $draf = $this->surat('draft');
        $terbit = $this->terbit();

        $this->actingAs($this->admin)->get(route('surat-keluar.index'))->assertSee('action="'.route('surat-keluar.destroy', $terbit).'"', false);
        $this->actingAs($this->staff)->get(route('surat-keluar.index'))->assertSee('action="'.route('surat-keluar.destroy', $draf).'"', false)->assertDontSee('action="'.route('surat-keluar.destroy', $terbit).'"', false);
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.index'))->assertDontSee('action="'.route('surat-keluar.destroy', $terbit).'"', false);
    }
}
