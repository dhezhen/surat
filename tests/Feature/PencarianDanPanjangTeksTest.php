<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratMasuk;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PencarianDanPanjangTeksTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::where('role', 'staff_prodi')->first();
    }

    public function test_pencarian_isi_surat_tidak_membedakan_huruf_besar_kecil(): void
    {
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->value('id'),
            'tanggal_surat' => '2026-11-20',
            'tujuan_surat' => 'PT Contoh Teknologi',
            'perihal' => 'Permohonan Kerja Praktik',
            'dinamis' => ['nama_mahasiswa' => 'Siti Nurhaliza', 'nim' => '2201001', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
        ])->assertSessionHasNoErrors();

        foreach (['nurhaliza', 'NURHALIZA'] as $q) {
            $this->get(route('pencarian', ['q' => $q]))->assertOk()->assertSee('Permohonan Kerja Praktik');
            $this->get(route('surat-keluar.index', ['q' => $q]))->assertOk()->assertSee('Permohonan Kerja Praktik');
        }
    }

    public function test_perihal_surat_masuk_sepanjang_batas_validasi_tersimpan_utuh(): void
    {
        Storage::fake('public');
        $perihal = str_repeat('a', 500);

        $this->actingAs($this->staff)->post(route('surat-masuk.store'), [
            'nomor_surat_asal' => '001/X/2026',
            'tanggal_surat' => '2026-11-20',
            'tanggal_diterima' => '2026-11-21',
            'pengirim' => 'Rektorat',
            'perihal' => $perihal,
            'kategori' => 'Rektorat',
            'file_scan' => UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($perihal, SuratMasuk::firstOrFail()->perihal);
    }
}
