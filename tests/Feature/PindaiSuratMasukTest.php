<?php

namespace Tests\Feature;

use App\Models\SuratMasuk;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PindaiSuratMasukTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
    }

    private function surat(array $ubah = []): array
    {
        return $ubah + [
            'nomor_surat_asal' => '7/UND/2026', 'tanggal_surat' => '2026-11-20', 'tanggal_diterima' => '2026-11-27',
            'pengirim' => 'Fakultas Teknik', 'perihal' => 'Undangan rapat', 'kategori' => 'Undangan', 'keterangan' => '',
            'file_scan' => UploadedFile::fake()->create('pindaian-20261127.pdf', 800, 'application/pdf'),
        ];
    }

    public function test_halaman_pindai_hanya_untuk_staff(): void
    {
        $this->actingAs($this->staff)->get(route('surat-masuk.pindai'))
            ->assertOk()->assertSee('Mulai memindai')->assertSee('Belum ada surat.');
        $this->actingAs($this->kaprodi)->get(route('surat-masuk.pindai'))->assertForbidden();
    }

    public function test_form_dan_daftar_menawarkan_kamera(): void
    {
        $this->actingAs($this->staff)->get(route('surat-masuk.create'))
            ->assertOk()->assertSee('Pindai dengan kamera')->assertSee(route('surat-masuk.pindai'));
        $this->actingAs($this->staff)->get(route('surat-masuk.index'))->assertSee('Pindai banyak surat');
    }

    public function test_simpan_lewat_fetch_mengembalikan_json_dengan_nomor_agenda_berurutan(): void
    {
        $pertama = $this->actingAs($this->staff)->postJson(route('surat-masuk.store'), $this->surat())->assertCreated();
        $kedua = $this->actingAs($this->staff)->postJson(route('surat-masuk.store'), $this->surat([
            'file_scan' => UploadedFile::fake()->image('pindaian.jpg', 1600, 2200),
        ]))->assertCreated();

        $this->assertSame('SM-2026-001', $pertama->json('nomor_agenda'));
        $this->assertSame('SM-2026-002', $kedua->json('nomor_agenda'));
        $surat = SuratMasuk::find($kedua->json('id'));
        $this->assertSame(route('surat-masuk.show', $surat), $kedua->json('url'));
        Storage::disk('public')->assertExists($surat->file_scan_path);
    }

    public function test_galat_validasi_dikembalikan_per_kolom(): void
    {
        $this->actingAs($this->staff)->postJson(route('surat-masuk.store'), $this->surat(['tanggal_surat' => '', 'kategori' => 'Tidak Ada']))
            ->assertUnprocessable()->assertJsonValidationErrors(['tanggal_surat', 'kategori']);
        $this->assertSame(0, SuratMasuk::count());
    }

    public function test_form_biasa_tetap_dialihkan_ke_arsip(): void
    {
        $this->actingAs($this->staff)->post(route('surat-masuk.store'), $this->surat())
            ->assertRedirect(route('surat-masuk.show', SuratMasuk::first()));
    }
}
