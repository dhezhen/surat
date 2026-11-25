<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\KopSuratService;
use App\Services\LogoService;
use App\Services\PenomoranService;
use App\Services\SuratRenderer;
use App\Support\Identitas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengaturanIdentitasTest extends TestCase
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
        $this->kaprodi->update(['signature_path' => null, 'nidn' => '12345']);
    }

    private function identitas(array $ubah = []): array
    {
        return $ubah + [
            'nama_universitas' => 'UNIVERSITAS CONTOH', 'nama_fakultas' => 'FAKULTAS TEKNIK', 'nama_prodi' => 'PROGRAM STUDI SISTEM INFORMASI',
            'alamat' => "Jalan Uji 1\nJalan Uji 2", 'kota' => 'Bandung', 'label_id_penandatangan' => 'NIDN',
        ];
    }

    private function penomoran(array $ubah = []): array
    {
        return $ubah + ['format_nomor' => '{urut}/{prodi}/{bulan}/{tahun}', 'nomor_pad' => 3, 'kode_prodi' => 'SI', 'kode_fakultas' => 'FT', 'nomor_awal' => null];
    }

    private function buatSurat(): SuratKeluar
    {
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-11-20', 'tujuan_surat' => 'PT Contoh', 'perihal' => 'KP',
            'dinamis' => ['nama_mahasiswa' => 'Budi', 'nim' => '1', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
            'aksi' => 'ajukan',
        ])->assertSessionHasNoErrors();

        return SuratKeluar::latest('id')->first();
    }

    private function terbitkan(?SuratKeluar $surat = null): SuratKeluar
    {
        $surat ??= $this->buatSurat();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat))->assertSessionHasNoErrors();

        return $surat->fresh();
    }

    private function kertasHtml(SuratKeluar $surat): string
    {
        return view('pdf.surat', ['surat' => $surat, 'pdf' => true] + SuratRenderer::kertas($surat))->render();
    }

    public function test_hanya_super_admin(): void
    {
        foreach ([$this->staff, $this->kaprodi] as $user) {
            $this->actingAs($user)->get(route('pengaturan.aplikasi'))->assertForbidden();
            $this->actingAs($user)->put(route('pengaturan.surat.identitas'), $this->identitas())->assertForbidden();
            $this->actingAs($user)->put(route('pengaturan.surat.penomoran'), $this->penomoran())->assertForbidden();
            $this->actingAs($user)->put(route('pengaturan.surat.kop.mode'), ['mode' => 'teks'])->assertForbidden();
            $this->actingAs($user)->put(route('pengaturan.aplikasi.update'), [])->assertForbidden();
            $this->actingAs($user)->post(route('pengaturan.aplikasi.logo'), [])->assertForbidden();
            $this->actingAs($user)->delete(route('pengaturan.aplikasi.logo.hapus'))->assertForbidden();
        }
        $this->actingAs($this->admin)->get(route('pengaturan.aplikasi'))->assertOk()->assertSee('SIP-TI');
        $this->actingAs($this->admin)->get(route('pengaturan.surat'))->assertOk()->assertSee('FKOM-UNIKU')
            ->assertSee('Penanda: {urut} {prodi} {fakultas} {bulan} {tahun}.', false);
    }

    public function test_identitas_baru_dipakai_surat_baru_tanpa_mengubah_surat_lama(): void
    {
        $lama = $this->terbitkan();

        $this->actingAs($this->admin)->put(route('pengaturan.surat.identitas'), $this->identitas())->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put(route('pengaturan.surat.kop.mode'), ['mode' => 'teks'])->assertSessionHasNoErrors();
        $baru = $this->terbitkan();

        $html = $this->kertasHtml($baru);
        foreach (['UNIVERSITAS CONTOH', 'Jalan Uji 2', 'Bandung,', 'NIDN. 12345'] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }

        $html = $this->kertasHtml($lama);
        $this->assertStringContainsString('Kuningan,', $html);
        $this->assertStringContainsString('NIK. 12345', $html);
        $this->assertStringContainsString('alt="Kop surat"', $html);
        $this->assertStringNotContainsString('UNIVERSITAS CONTOH', $html);
    }

    public function test_arsip_lama_tanpa_identitas_memakai_nilai_config(): void
    {
        $surat = $this->terbitkan();
        $path = 'arsip-surat/'.$surat->uuid_validasi.'.json';
        $arsip = json_decode(Storage::disk('local')->get($path), true);
        unset($arsip['identitas'], $arsip['logo']);
        Storage::disk('local')->put($path, json_encode($arsip));

        Identitas::simpan(['kota' => 'Bandung']);

        $this->assertSame('Kuningan', SuratRenderer::kertas($surat)['identitas']['kota']);
    }

    public function test_format_dan_digit_nomor_dipakai(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.surat.penomoran'), $this->penomoran())->assertSessionHasNoErrors();

        $surat = $this->terbitkan();

        $this->assertMatchesRegularExpression('#^001/SI/[IVX]+/'.now()->year.'$#', $surat->nomor_surat_resmi);
    }

    public function test_nomor_awal_meneruskan_buku_agenda_dan_kembali_ke_satu_tahun_depan(): void
    {
        $url = route('pengaturan.surat.penomoran');
        $this->actingAs($this->admin)->put($url, $this->penomoran(['nomor_awal' => 120]))->assertSessionHasNoErrors();

        $this->assertStringStartsWith('120/', PenomoranService::perkiraan());
        $this->assertSame(120, $this->terbitkan()->nomor_urut);
        $this->assertSame(121, $this->terbitkan()->nomor_urut);

        $this->travelTo(now()->addYear()->startOfYear()->addDay());
        $this->assertSame(1, $this->terbitkan()->nomor_urut);
    }

    public function test_nomor_awal_tidak_boleh_menabrak_nomor_terpakai(): void
    {
        $this->terbitkan();
        $url = route('pengaturan.surat.penomoran');

        $this->actingAs($this->admin)->put($url, $this->penomoran(['nomor_awal' => 1]))->assertSessionHasErrors('nomor_awal');
        $this->actingAs($this->admin)->put($url, $this->penomoran(['nomor_awal' => 2]))->assertSessionHasNoErrors();
        // Setelah terlewati, form tidak boleh mengisi ulang nilai yang akan ditolak.
        $this->terbitkan();
        $this->actingAs($this->admin)->get(route('pengaturan.surat'))->assertViewHas('nomorAwal', null);
    }

    public function test_format_nomor_yang_berisiko_ditolak(): void
    {
        $url = route('pengaturan.surat.penomoran');
        foreach (['{prodi}/{tahun}', '{urut}/{prodi}', '{urut}/{semester}/{tahun}', '<b>{urut}</b>/{tahun}'] as $format) {
            $this->actingAs($this->admin)->put($url, $this->penomoran(['format_nomor' => $format]))->assertSessionHasErrors('format_nomor');
        }
        $this->actingAs($this->admin)->put($url, $this->penomoran(['nomor_pad' => 9]))->assertSessionHasErrors('nomor_pad');
        $this->assertSame(config('prodi.format_nomor'), Identitas::ambil('format_nomor'));
    }

    public function test_nama_aplikasi_dan_unit_tampil_di_halaman_masuk_dan_menu(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.update'), [
            'nama_aplikasi' => 'SURAT-SI', 'nama_unit' => 'Program Studi Sistem Informasi', 'kategori_surat_masuk' => "Rektorat\nMitra",
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('SURAT-SI')->assertSee('Program Studi Sistem Informasi');
        auth()->logout();
        $this->get('/login')->assertSee('SURAT-SI')->assertSee('Program Studi Sistem Informasi')->assertDontSee('Teknik Informatika');
    }

    public function test_kategori_baru_berlaku_dan_arsip_lama_tetap_bisa_diubah_serta_disaring(): void
    {
        $arsip = SuratMasuk::create([
            'nomor_agenda' => 'SM-2026-001', 'nomor_surat_asal' => '1', 'tanggal_surat' => '2026-11-01', 'tanggal_diterima' => '2026-11-02',
            'pengirim' => 'X', 'perihal' => 'Y', 'kategori' => 'Fakultas', 'file_scan_path' => 'surat-masuk/a.pdf',
        ]);
        Identitas::simpan(['kategori_surat_masuk' => "Rektorat\nMitra"]);

        $data = $arsip->only(['nomor_surat_asal', 'pengirim', 'perihal']) + ['tanggal_surat' => '2026-11-01', 'tanggal_diterima' => '2026-11-02'];
        $this->actingAs($this->staff)->put(route('surat-masuk.update', $arsip), $data + ['kategori' => 'Fakultas'])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->put(route('surat-masuk.update', $arsip), $data + ['kategori' => 'Undangan'])->assertSessionHasErrors('kategori');
        $this->actingAs($this->staff)->put(route('surat-masuk.update', $arsip), $data + ['kategori' => 'Mitra'])->assertSessionHasNoErrors();

        $this->assertSame(['Rektorat', 'Mitra'], SuratMasuk::pilihanKategori());
        // fresh() karena objek lama tidak tahu kategorinya sudah diubah lewat permintaan HTTP.
        $arsip->fresh()->update(['kategori' => 'Fakultas']);
        $this->assertContains('Fakultas', SuratMasuk::kategoriUntukSaring());
        $this->actingAs($this->staff)->get(route('surat-masuk.create'))->assertOk()->assertSee('Mitra');
    }

    public function test_kategori_tidak_layak_ditolak(): void
    {
        foreach (['   ', "A\na", str_repeat('x', 51), implode("\n", range(1, 21))] as $isi) {
            $this->actingAs($this->admin)->put(route('pengaturan.aplikasi.update'), [
                'nama_aplikasi' => 'A', 'nama_unit' => 'B', 'kategori_surat_masuk' => $isi,
            ])->assertSessionHasErrors('kategori_surat_masuk');
        }
    }

    public function test_logo_diunggah_tersaji_publik_dan_bisa_dikembalikan(): void
    {
        $this->get(route('logo'))->assertNotFound();
        $berkas = fn (int $w, int $h) => UploadedFile::fake()->image('logo.png', $w, $h);

        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.logo'), ['logo' => $berkas(64, 64)])->assertSessionHasErrors('logo');
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.logo'), ['logo' => $berkas(800, 200)])->assertSessionHasErrors('logo');
        $this->actingAs($this->admin)->post(route('pengaturan.aplikasi.logo'), ['logo' => $berkas(800, 800)])->assertSessionHasNoErrors();

        $url = LogoService::url();
        auth()->logout();
        $this->get('/login')->assertSee($url, false);
        $res = $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
        $this->assertSame(400, getimagesizefromstring($res->getContent())[0]);

        $this->actingAs($this->admin)->delete(route('pengaturan.aplikasi.logo.hapus'));
        $this->assertStringContainsString('assets/logo-uniku.png', LogoService::url());
        $this->assertCount(0, Storage::disk('local')->files('pengaturan'));
    }

    public function test_mode_kop_bisa_dikembalikan_ke_gambar(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.surat.kop.mode'), ['mode' => 'teks']);
        $this->assertNull(KopSuratService::dataUri());

        $this->actingAs($this->admin)->put(route('pengaturan.surat.kop.mode'), ['mode' => 'gambar']);
        $this->assertNotNull(KopSuratService::dataUri());
        $this->actingAs($this->admin)->put(route('pengaturan.surat.kop.mode'), ['mode' => 'lain'])->assertSessionHasErrors('mode');
    }
}
