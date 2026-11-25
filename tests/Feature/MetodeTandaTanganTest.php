<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetodeTandaTanganTest extends TestCase
{
    use RefreshDatabase;

    private const GAMBAR_QR = 'data:image/svg+xml;base64';
    private const GAMBAR_TTD = 'data:image/png;base64,';

    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
        $this->kaprodi->update(['signature_path' => null]);
    }

    private function buatSurat(?string $metode = null): SuratKeluar
    {
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), array_filter([
            'jenis_surat_id' => $jenis->id,
            'tanggal_surat' => '2026-11-20',
            'tujuan_surat' => 'PT Contoh Teknologi',
            'perihal' => 'Permohonan Kerja Praktik',
            'dinamis' => ['nama_mahasiswa' => 'Budi', 'nim' => '2201001', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
            'metode_ttd' => $metode,
            'aksi' => 'ajukan',
        ]))->assertSessionHasNoErrors();

        return SuratKeluar::latest('id')->first();
    }

    private function beriTandaTangan(): void
    {
        // PNG 1x1 yang sah, cukup untuk diperiksa keberadaannya pada kertas surat.
        Storage::disk('public')->put('tanda-tangan/uji.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $this->kaprodi->update(['signature_path' => 'tanda-tangan/uji.png']);
    }

    private function setujui(SuratKeluar $surat, ?string $metode = null)
    {
        return $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat), array_filter(['metode_ttd' => $metode]));
    }

    /** HTML kertas surat, tanpa logo dan kop yang juga berupa data URI. */
    private function blokTandaTangan(SuratKeluar $surat): string
    {
        $html = $this->actingAs($this->staff)->get(route('surat-keluar.show', $surat))->assertOk()->getContent();

        return explode('</table>', explode('class="kertas-ttd"', $html)[1])[0];
    }

    public function test_ttd_basah_tanpa_qr_maupun_gambar(): void
    {
        $this->beriTandaTangan();
        $surat = $this->buatSurat('basah');
        $this->setujui($surat)->assertSessionHasNoErrors();

        $this->assertSame('basah', $surat->fresh()->metode_ttd);
        $blok = $this->blokTandaTangan($surat);
        $this->assertStringNotContainsString(self::GAMBAR_QR, $blok);
        $this->assertStringNotContainsString(self::GAMBAR_TTD, $blok);
        $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $surat))->assertOk();
    }

    public function test_ttd_qr_hanya_menampilkan_qr(): void
    {
        $this->beriTandaTangan();
        $surat = $this->buatSurat('qr');
        $this->setujui($surat)->assertSessionHasNoErrors();

        $blok = $this->blokTandaTangan($surat);
        $this->assertStringContainsString(self::GAMBAR_QR, $blok);
        $this->assertStringNotContainsString(self::GAMBAR_TTD, $blok);
    }

    public function test_ttd_digital_menampilkan_gambar_dan_qr(): void
    {
        $this->beriTandaTangan();
        $surat = $this->buatSurat('digital');
        $this->setujui($surat)->assertSessionHasNoErrors();

        $blok = $this->blokTandaTangan($surat);
        $this->assertStringContainsString(self::GAMBAR_QR, $blok);
        $this->assertStringContainsString(self::GAMBAR_TTD, $blok);
    }

    public function test_kaprodi_dapat_mengganti_metode_saat_menyetujui(): void
    {
        $surat = $this->buatSurat('qr');
        $this->setujui($surat, 'basah')->assertSessionHasNoErrors();

        $this->assertSame('basah', $surat->fresh()->metode_ttd);
        $this->get(route('validasi', $surat->uuid_validasi))->assertOk()->assertSee('TTD manual (basah)');
    }

    public function test_ttd_digital_tanpa_gambar_tidak_bisa_diterbitkan(): void
    {
        $surat = $this->buatSurat('digital');
        $this->setujui($surat)->assertSessionHasErrors('aksi');

        $this->assertSame('menunggu_approval', $surat->fresh()->status);
        $this->assertNull($surat->fresh()->nomor_urut);
    }

    public function test_tanpa_pilihan_mengikuti_ketersediaan_gambar(): void
    {
        $tanpa = $this->buatSurat();
        $this->setujui($tanpa);
        $this->assertSame('qr', $tanpa->fresh()->metode_ttd);

        $this->beriTandaTangan();
        $dengan = $this->buatSurat();
        $this->setujui($dengan);
        $this->assertSame('digital', $dengan->fresh()->metode_ttd);
    }

    public function test_metode_tidak_dikenal_ditolak(): void
    {
        $surat = $this->buatSurat('qr');
        $this->setujui($surat, 'cap-jempol')->assertSessionHasErrors('metode_ttd');
        $this->assertSame('menunggu_approval', $surat->fresh()->status);
    }

    public function test_pilihan_tampil_di_form_dan_halaman_persetujuan(): void
    {
        $this->actingAs($this->staff)->get(route('surat-keluar.create'))->assertOk()
            ->assertSee('TTD manual (basah)')->assertSee('TTD QR Code')->assertSee('TTD digital');

        $surat = $this->buatSurat('basah');
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.show', $surat))->assertOk()
            ->assertSee('name="metode_ttd"', false)->assertSee('[Ruang tanda tangan basah]');
    }
}
