<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Services\SuratRenderer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ValidasiPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
    }

    private function buatSurat(bool $terbitkan, string $metode = 'qr', ?bool $tampilPdf = true): SuratKeluar
    {
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id,
            'tanggal_surat' => '2026-11-20',
            'tujuan_surat' => 'PT Contoh Teknologi',
            'perihal' => 'Permohonan Kerja Praktik',
            'dinamis' => ['nama_mahasiswa' => 'Budi', 'nim' => '2201001', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
            'metode_ttd' => $metode,
            'aksi' => 'ajukan',
        ])->assertSessionHasNoErrors();

        $surat = SuratKeluar::latest('id')->first();
        if ($terbitkan) {
            $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat), $tampilPdf === null ? [] : ['tampil_pdf_publik' => (int) $tampilPdf])
                ->assertSessionHasNoErrors();
        }
        auth()->logout();

        return $surat->fresh();
    }

    public function test_tamu_melihat_dan_mengunduh_pdf_surat_terbit(): void
    {
        $surat = $this->buatSurat(true);

        $this->get(route('validasi', $surat->uuid_validasi))->assertOk()
            ->assertSee('Dokumen surat')
            ->assertSee(route('validasi.pdf', $surat->uuid_validasi), false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('tanda tangan basah dan cap');

        $lihat = $this->get(route('validasi.pdf', $surat->uuid_validasi))->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringStartsWith('%PDF', $lihat->getContent());
        $this->assertStringStartsWith('inline', $lihat->headers->get('content-disposition'));

        $unduh = $this->get(route('validasi.pdf', [$surat->uuid_validasi, 'unduh' => 1]))->assertOk();
        $this->assertStringStartsWith('attachment', $unduh->headers->get('content-disposition'));
    }

    public function test_surat_basah_diberi_keterangan_tanpa_tanda_tangan(): void
    {
        $surat = $this->buatSurat(true, 'basah');

        $this->get(route('validasi', $surat->uuid_validasi))->assertOk()->assertSee('tanda tangan basah dan cap');
    }

    public function test_pdf_publik_ditolak_untuk_surat_belum_terbit_terhapus_atau_tak_dikenal(): void
    {
        $menunggu = $this->buatSurat(false);
        $this->get(route('validasi.pdf', $menunggu->uuid_validasi))->assertNotFound();

        $terhapus = $this->buatSurat(true);
        $terhapus->delete();
        $this->get(route('validasi.pdf', $terhapus->uuid_validasi))->assertNotFound();

        $this->get(route('validasi.pdf', '00000000-0000-0000-0000-000000000000'))->assertNotFound();
        $this->get(route('validasi', $menunggu->uuid_validasi))->assertNotFound()->assertDontSee('Dokumen surat');
    }

    public function test_pdf_publik_tertutup_secara_bawaan(): void
    {
        $surat = $this->buatSurat(true, tampilPdf: null);

        $this->assertFalse($surat->tampil_pdf_publik);
        $this->get(route('validasi', $surat->uuid_validasi))->assertOk()
            ->assertSee('DOKUMEN SAH')->assertSee('tidak ditampilkan untuk umum')->assertDontSee('Dokumen surat');
        $this->get(route('validasi.pdf', $surat->uuid_validasi))->assertNotFound();
        $this->get(route('validasi.pdf', [$surat->uuid_validasi, 'unduh' => 1]))->assertNotFound();
    }

    public function test_nilai_awal_mengikuti_jenis_surat_dan_bisa_diubah_kaprodi(): void
    {
        JenisSurat::query()->update(['pdf_publik' => true]);
        $this->assertTrue($this->buatSurat(true, tampilPdf: null)->tampil_pdf_publik);
        $this->assertFalse($this->buatSurat(true, tampilPdf: false)->tampil_pdf_publik);
    }

    public function test_mengaktifkan_jenis_surat_tidak_membuka_surat_lama(): void
    {
        $surat = $this->buatSurat(true, tampilPdf: null);
        JenisSurat::query()->update(['pdf_publik' => true]);

        $this->get(route('validasi.pdf', $surat->uuid_validasi))->assertNotFound();
    }

    public function test_kaprodi_membuka_dan_menutup_pdf_surat_terbit(): void
    {
        $surat = $this->buatSurat(true, tampilPdf: false);
        $url = route('surat-keluar.pdf-publik', $surat);

        $this->actingAs($this->staff)->put($url, ['tampil_pdf_publik' => 1])->assertForbidden();
        $this->actingAs($this->kaprodi)->put($url, ['tampil_pdf_publik' => 1])->assertSessionHasNoErrors();
        $this->get(route('validasi.pdf', $surat->uuid_validasi))->assertOk();
        $this->actingAs($this->kaprodi)->put($url, ['tampil_pdf_publik' => 0]);
        $this->get(route('validasi.pdf', $surat->uuid_validasi))->assertNotFound();

        $menunggu = $this->buatSurat(false);
        $this->actingAs($this->kaprodi)->put(route('surat-keluar.pdf-publik', $menunggu), ['tampil_pdf_publik' => 1])->assertForbidden();
    }

    public function test_hanya_pdf_publik_yang_diberi_watermark(): void
    {
        $surat = $this->buatSurat(true);
        $salinan = [];
        View::composer('pdf.surat', function ($view) use (&$salinan) {
            $salinan[] = $view->getData()['salinan'];
        });

        $publik = $this->get(route('validasi.pdf', [$surat->uuid_validasi, 'unduh' => 1]))->assertOk();
        $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $surat))->assertOk();

        $this->assertSame([true, false], $salinan);
        $this->assertStringContainsString('Salinan-', $publik->headers->get('content-disposition'));
        $this->assertStringContainsString('SALINAN VERIFIKASI', view('pdf.surat', ['surat' => $surat, 'pdf' => true, 'salinan' => true] + SuratRenderer::kertas($surat))->render());
    }

    public function test_gambar_tanda_tangan_pada_salinan_publik_dicap(): void
    {
        Storage::fake('public');
        $gambar = imagecreatetruecolor(300, 100);
        imagefill($gambar, 0, 0, imagecolorallocate($gambar, 255, 255, 255));
        ob_start();
        imagepng($gambar);
        Storage::disk('public')->put('tanda-tangan/uji.png', ob_get_clean());
        $this->kaprodi->update(['signature_path' => 'tanda-tangan/uji.png']);

        $surat = $this->buatSurat(true, 'digital');
        $ttd = [];
        View::composer('pdf.surat', function ($view) use (&$ttd) {
            $ttd[] = $view->getData()['ttd'];
        });

        $this->get(route('validasi.pdf', $surat->uuid_validasi))->assertOk();
        $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $surat))->assertOk();

        [$publik, $asli] = $ttd;
        $this->assertNotNull($publik);
        $this->assertNotSame($asli, $publik);
        $this->assertNull(SuratRenderer::capSalinan('data:image/png;base64,'.base64_encode('bukan gambar')));
    }

    public function test_isi_surat_terbit_tidak_ikut_berubah(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tanda-tangan/lama.png', 'GAMBAR-LAMA');
        $this->kaprodi->update(['signature_path' => 'tanda-tangan/lama.png', 'name' => 'Nama Saat Terbit']);
        $surat = $this->buatSurat(true, 'digital');
        Storage::disk('local')->assertExists('arsip-surat/'.$surat->uuid_validasi.'.json');

        $surat->jenisSurat->update(['template_html' => '<p>Template baru {{nomor_surat}}</p>']);
        Storage::disk('public')->put('tanda-tangan/baru.png', 'GAMBAR-BARU');
        $this->kaprodi->update(['signature_path' => 'tanda-tangan/baru.png', 'name' => 'Nama Sesudahnya']);

        $kertas = SuratRenderer::kertas($surat->fresh());
        $this->assertStringContainsString('Budi', $kertas['isi']);
        $this->assertStringNotContainsString('Template baru', $kertas['isi']);
        $this->assertSame('data:image/png;base64,'.base64_encode('GAMBAR-LAMA'), $kertas['ttd']);
        $this->assertSame('Nama Saat Terbit', $kertas['penandatangan']->name);
    }

    public function test_koreksi_nomor_tetap_tampil_pada_surat_yang_dibekukan(): void
    {
        $surat = $this->buatSurat(false);
        $surat->jenisSurat->update(['template_html' => '<p>Nomor: {{nomor_surat}}</p>']);
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat));

        $this->actingAs($this->staff)->put(route('surat-keluar.nomor', $surat), ['nomor_surat_resmi' => '99/KOREKSI/2026'])->assertSessionHasNoErrors();

        $this->assertStringContainsString('Nomor: 99/KOREKSI/2026', SuratRenderer::kertas($surat->fresh())['isi']);
    }

    public function test_surat_lama_dibekukan_lewat_perintah_arsip(): void
    {
        $surat = $this->buatSurat(true);
        Storage::disk('local')->delete('arsip-surat/'.$surat->uuid_validasi.'.json');

        $this->artisan('surat:arsipkan')->assertSuccessful();

        Storage::disk('local')->assertExists('arsip-surat/'.$surat->uuid_validasi.'.json');
    }

    public function test_pengaturan_pdf_publik_pada_jenis_surat(): void
    {
        $data = ['nama_surat' => 'Undangan Umum', 'template_html' => '<p>{{perihal}}</p>', 'form_schema' => '[]'];

        $this->actingAs($this->staff)->post(route('jenis-surat.store'), $data)->assertSessionHasNoErrors();
        $jenis = JenisSurat::where('nama_surat', 'Undangan Umum')->first();
        $this->assertFalse($jenis->pdf_publik);

        $this->actingAs($this->staff)->put(route('jenis-surat.update', $jenis), $data + ['pdf_publik' => '1']);
        $this->assertTrue($jenis->fresh()->pdf_publik);
    }

    public function test_tombol_unduh_di_aplikasi_mengunduh_berkas(): void
    {
        $surat = $this->buatSurat(true);

        $unduh = $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $surat))->assertOk();
        $this->assertStringStartsWith('attachment', $unduh->headers->get('content-disposition'));

        $buka = $this->actingAs($this->staff)->get(route('surat-keluar.pdf', [$surat, 'unduh' => 0]))->assertOk();
        $this->assertStringStartsWith('inline', $buka->headers->get('content-disposition'));
    }
}
