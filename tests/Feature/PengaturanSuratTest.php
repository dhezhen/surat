<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Services\KopSuratService;
use App\Services\SuratRenderer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengaturanSuratTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'super_admin')->first();
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
        $this->kaprodi->update(['signature_path' => null]);
    }

    private function gambar(int $lebar, int $tinggi, string $nama = 'gambar.png'): UploadedFile
    {
        $im = imagecreatetruecolor($lebar, $tinggi);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagesetthickness($im, 6);
        imageline($im, 20, $tinggi - 20, $lebar - 20, 20, imagecolorallocate($im, 10, 20, 60));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent($nama, ob_get_clean());
    }

    private function terbitkanSurat(): SuratKeluar
    {
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-11-20', 'tujuan_surat' => 'PT Contoh', 'perihal' => 'KP',
            'dinamis' => ['nama_mahasiswa' => 'Budi', 'nim' => '1', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
            'aksi' => 'ajukan',
        ]);
        $surat = SuratKeluar::latest('id')->first();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat))->assertSessionHasNoErrors();

        return $surat->fresh();
    }

    public function test_hanya_super_admin_yang_bisa_membuka_dan_mengubah(): void
    {
        $this->get(route('pengaturan.surat'))->assertRedirect('/login');

        foreach ([$this->staff, $this->kaprodi] as $user) {
            $this->actingAs($user)->get(route('pengaturan.surat'))->assertForbidden();
            $this->actingAs($user)->post(route('pengaturan.surat.kop'), ['kop' => $this->gambar(1200, 200)])->assertForbidden();
            $this->actingAs($user)->delete(route('pengaturan.surat.kop.hapus'))->assertForbidden();
            $this->actingAs($user)->post(route('pengaturan.surat.ttd', $this->kaprodi), ['tanda_tangan' => $this->gambar(400, 160)])->assertForbidden();
            $this->actingAs($user)->delete(route('pengaturan.surat.ttd.hapus', $this->kaprodi))->assertForbidden();
        }

        $this->actingAs($this->admin)->get(route('pengaturan.surat'))->assertOk()
            ->assertSee('Kop bawaan aplikasi sedang dipakai')->assertSee($this->kaprodi->name);
    }

    public function test_kop_unggahan_dipakai_surat_baru_tanpa_mengubah_surat_lama(): void
    {
        $bawaan = KopSuratService::dataUri();
        $lama = $this->terbitkanSurat();

        $this->actingAs($this->admin)->post(route('pengaturan.surat.kop'), ['kop' => $this->gambar(2400, 400)])->assertSessionHasNoErrors();

        $baru = KopSuratService::dataUri();
        $this->assertNotSame($bawaan, $baru);
        $this->assertSame(2000, getimagesizefromstring(base64_decode(explode(',', $baru)[1]))[0], 'diperkecil');
        $this->assertSame($bawaan, SuratRenderer::kertas($lama)['kop']);
        $this->assertSame($baru, SuratRenderer::kertas($this->terbitkanSurat())['kop']);
        $this->actingAs($this->admin)->get(route('pengaturan.surat'))->assertSee('Kop unggahan sedang dipakai');
    }

    public function test_mengganti_dan_menghapus_kop_tidak_menyisakan_berkas(): void
    {
        $url = route('pengaturan.surat.kop');
        $this->actingAs($this->admin)->post($url, ['kop' => $this->gambar(1200, 200)]);
        $this->actingAs($this->admin)->post($url, ['kop' => $this->gambar(1400, 200)]);
        $this->assertCount(1, Storage::disk('local')->files('pengaturan'));

        $this->actingAs($this->admin)->delete(route('pengaturan.surat.kop.hapus'));

        $this->assertCount(0, Storage::disk('local')->files('pengaturan'));
        $this->assertFalse(KopSuratService::pakaiUnggahan());
    }

    public function test_kop_yang_tidak_layak_ditolak(): void
    {
        $url = route('pengaturan.surat.kop');
        foreach ([$this->gambar(600, 100), $this->gambar(1200, 900), UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')] as $berkas) {
            $this->actingAs($this->admin)->post($url, ['kop' => $berkas])->assertSessionHasErrors('kop');
        }
        $this->actingAs($this->admin)->post($url, [])->assertSessionHasErrors('kop');
        $this->assertFalse(KopSuratService::pakaiUnggahan());
    }

    public function test_admin_mengunggah_dan_menghapus_tanda_tangan_kaprodi(): void
    {
        $this->actingAs($this->admin)->post(route('pengaturan.surat.ttd', $this->kaprodi), ['tanda_tangan' => $this->gambar(400, 160)])
            ->assertSessionHasNoErrors();

        $path = $this->kaprodi->fresh()->signature_path;
        Storage::disk('public')->assertExists($path);
        $this->assertNull($this->admin->fresh()->signature_path);
        $this->actingAs($this->admin)->get(route('pengaturan.surat'))->assertSee('data:image/png;base64', false)->assertSee('Ganti berkas tanda tangan');

        $this->actingAs($this->admin)->delete(route('pengaturan.surat.ttd.hapus', $this->kaprodi));
        $this->assertNull($this->kaprodi->fresh()->signature_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_tanda_tangan_hanya_untuk_kaprodi_dan_berkas_gambar(): void
    {
        $this->actingAs($this->admin)->post(route('pengaturan.surat.ttd', $this->staff), ['tanda_tangan' => $this->gambar(400, 160)])->assertNotFound();
        $this->actingAs($this->admin)->post(route('pengaturan.surat.ttd', $this->kaprodi), ['tanda_tangan' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('tanda_tangan');
        $this->assertNull($this->kaprodi->fresh()->signature_path);
    }
}
