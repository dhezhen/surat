<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Services\PenomoranService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArsipSuratTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->admin = User::where('email', 'ti@uniku.ac.id')->first();
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
    }

    private function jenisId(): int
    {
        return JenisSurat::where('nama_surat', 'Surat Baru (Isi Saja)')->first()->id;
    }

    private function dataManual(array $ubah = []): array
    {
        return $ubah + [
            'nomor_surat_resmi' => '087/TI-S1/FKOM/III/2024',
            'tanggal_surat' => '2024-03-12',
            'jenis_surat_id' => $this->jenisId(),
            'perihal' => 'Surat Keterangan Aktif Kuliah',
            'tujuan_surat' => 'Mahasiswa Uji',
            'penyetuju_id' => $this->kaprodi->id,
            'berkas' => UploadedFile::fake()->create('pindai.pdf', 120, 'application/pdf'),
            'tampil_pdf_publik' => '0',
            'keterangan_arsip' => 'Dari buku agenda 2024',
        ];
    }

    private function tambahManual(array $ubah = []): SuratKeluar
    {
        $this->actingAs($this->admin)->post(route('arsip.store'), $this->dataManual($ubah))->assertSessionHasNoErrors();

        return SuratKeluar::where('sumber', 'manual')->latest('id')->firstOrFail();
    }

    private function terbitDariSistem(): SuratKeluar
    {
        $s = SuratKeluar::create([
            'jenis_surat_id' => $this->jenisId(), 'pembuat_id' => $this->staff->id, 'tanggal_surat' => now(),
            'tujuan_surat' => 'Dekan', 'perihal' => 'Surat dari sistem', 'data_dinamis' => ['isi_surat' => '<p>x</p>'],
            'status' => 'menunggu_approval',
        ]);
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s));

        return $s->fresh();
    }

    public function test_hanya_super_admin_yang_mengelola_arsip(): void
    {
        foreach ([$this->staff, $this->kaprodi] as $user) {
            $this->actingAs($user)->get(route('arsip.index'))->assertForbidden();
            $this->actingAs($user)->post(route('arsip.store'), $this->dataManual())->assertForbidden();
        }
        $this->assertSame(0, SuratKeluar::where('sumber', 'manual')->count());

        $this->actingAs($this->admin)->get(route('arsip.index'))->assertOk()->assertSee('Arsip Surat');
        $this->actingAs($this->admin)->get(route('arsip.create'))->assertOk()->assertSee('Tambah arsip manual');
    }

    public function test_tambah_arsip_manual_terbit_tanpa_menggeser_penomoran(): void
    {
        $perkiraan = PenomoranService::perkiraan();
        $s = $this->tambahManual();

        $this->assertTrue($s->isManual());
        $this->assertTrue($s->isTerbit());
        $this->assertSame(2024, $s->tahun_terbit);
        $this->assertNull($s->nomor_urut);
        $this->assertSame($this->admin->id, $s->pembuat_id);
        Storage::disk('local')->assertExists($s->berkas_path);
        $this->assertSame($perkiraan, PenomoranService::perkiraan());

        $this->actingAs($this->admin)->get(route('arsip.index'))->assertSee('087/TI-S1/FKOM/III/2024')->assertSee('Arsip manual');
        $this->actingAs($this->staff)->get(route('surat-keluar.show', $s))->assertOk()->assertSee('Arsip manual');
        $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $s))->assertOk()->assertDownload('Surat-087-TI-S1-FKOM-III-2024.pdf');
    }

    public function test_validasi_publik_arsip_manual_mengikuti_pilihan_pdf(): void
    {
        $s = $this->tambahManual();
        $this->get(route('validasi', $s->uuid_validasi))->assertOk()->assertSee('tercatat resmi dalam arsip persuratan', false);
        $this->get(route('validasi.pdf', $s->uuid_validasi))->assertNotFound();

        $publik = $this->tambahManual(['nomor_surat_resmi' => '088/TI-S1/FKOM/III/2024', 'tampil_pdf_publik' => '1']);
        $this->get(route('validasi.pdf', $publik->uuid_validasi))->assertOk();
    }

    public function test_nomor_harus_unik_termasuk_surat_terhapus(): void
    {
        $sistem = $this->terbitDariSistem();
        $sistem->delete();

        $this->actingAs($this->admin)->post(route('arsip.store'), $this->dataManual(['nomor_surat_resmi' => $sistem->nomor_surat_resmi]))
            ->assertSessionHasErrors('nomor_surat_resmi');
        $this->assertSame(0, SuratKeluar::where('sumber', 'manual')->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('arsip-manual'));
    }

    public function test_berkas_wajib_pdf_dan_tanggal_tidak_di_masa_depan(): void
    {
        $this->actingAs($this->admin)->post(route('arsip.store'), $this->dataManual([
            'berkas' => UploadedFile::fake()->image('foto.jpg'),
            'tanggal_surat' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors(['berkas', 'tanggal_surat']);
    }

    public function test_ubah_surat_sistem_hanya_mengubah_metadata(): void
    {
        $s = $this->terbitDariSistem();

        $this->actingAs($this->admin)->get(route('arsip.edit', $s))->assertOk()->assertSee('isinya dibekukan');
        $this->actingAs($this->admin)->put(route('arsip.update', $s), [
            'nomor_surat_resmi' => '999/KOREKSI/2026',
            'alasan_koreksi' => 'Salah ketik',
            'tampil_pdf_publik' => '1',
            'keterangan_arsip' => 'Dikoreksi admin',
            'perihal' => 'Diubah diam-diam',
            'tujuan_surat' => 'Orang lain',
        ])->assertRedirect(route('arsip.index'));

        $s->refresh();
        $this->assertSame('999/KOREKSI/2026', $s->nomor_surat_resmi);
        $this->assertTrue($s->tampil_pdf_publik);
        $this->assertSame('Dikoreksi admin', $s->keterangan_arsip);
        $this->assertSame('Surat dari sistem', $s->perihal);
        $this->assertSame('Dekan', $s->tujuan_surat);
    }

    public function test_ubah_arsip_manual_dan_ganti_berkas(): void
    {
        $s = $this->tambahManual();
        $lama = $s->berkas_path;

        $this->actingAs($this->admin)->put(route('arsip.update', $s), $this->dataManual([
            'perihal' => 'Perihal baru',
            'tanggal_surat' => '2023-12-01',
            'berkas' => UploadedFile::fake()->create('baru.pdf', 80, 'application/pdf'),
        ]))->assertRedirect(route('arsip.index'));

        $s->refresh();
        $this->assertSame('Perihal baru', $s->perihal);
        $this->assertSame(2023, $s->tahun_terbit);
        $this->assertNotSame($lama, $s->berkas_path);
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($s->berkas_path);

        // Tanpa berkas baru, berkas lama dipertahankan.
        $this->actingAs($this->admin)->put(route('arsip.update', $s), array_diff_key($this->dataManual(['perihal' => 'Lagi']), ['berkas' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame($s->berkas_path, $s->fresh()->berkas_path);
    }

    public function test_hapus_wajib_alasan_lalu_bisa_dipulihkan(): void
    {
        $s = $this->tambahManual();

        $this->actingAs($this->admin)->delete(route('arsip.destroy', $s), ['alasan_hapus' => ''])->assertSessionHasErrors('alasan_hapus');
        $this->assertNotSoftDeleted($s);

        $this->actingAs($this->admin)->delete(route('arsip.destroy', $s), ['alasan_hapus' => 'Surat ganda'])->assertRedirect(route('arsip.index'));
        $this->assertSoftDeleted($s);
        $this->get(route('validasi', $s->uuid_validasi))->assertNotFound();
        $this->actingAs($this->admin)->get(route('arsip.index', ['tab' => 'terhapus']))->assertSee('Surat ganda');
        $this->actingAs($this->admin)->get(route('arsip.index'))->assertDontSee('087/TI-S1/FKOM/III/2024');

        $this->actingAs($this->admin)->post(route('arsip.pulihkan', $s))->assertRedirect(route('arsip.index'));
        $s->refresh();
        $this->assertFalse($s->trashed());
        $this->assertNull($s->alasan_hapus);
        $this->assertNull($s->dihapus_oleh_id);
    }

    public function test_daftar_arsip_hanya_surat_terbit_dan_bisa_difilter(): void
    {
        SuratKeluar::create([
            'jenis_surat_id' => $this->jenisId(), 'pembuat_id' => $this->staff->id, 'tanggal_surat' => now(),
            'tujuan_surat' => 'Dekan', 'perihal' => 'Masih draf', 'data_dinamis' => [], 'status' => 'draft',
        ]);
        $sistem = $this->terbitDariSistem();
        $this->tambahManual();

        $this->actingAs($this->admin)->get(route('arsip.index'))
            ->assertDontSee('Masih draf')->assertSee('Surat dari sistem')->assertSee('Surat Keterangan Aktif Kuliah');
        $this->actingAs($this->admin)->get(route('arsip.index', ['sumber' => 'manual']))
            ->assertDontSee('Surat dari sistem')->assertSee('Surat Keterangan Aktif Kuliah');
        $this->actingAs($this->admin)->get(route('arsip.index', ['tahun' => 2024]))
            ->assertDontSee($sistem->nomor_surat_resmi)->assertSee('087/TI-S1/FKOM/III/2024');
    }
}
