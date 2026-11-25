<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlurSuratTest extends TestCase
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

    private function buatSurat(string $aksi = 'ajukan', string $nama = 'Budi Santoso')
    {
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();

        return $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id,
            'tanggal_surat' => '2026-11-20',
            'tujuan_surat' => 'PT Contoh Teknologi',
            'perihal' => 'Permohonan Kerja Praktik',
            'dinamis' => ['nama_mahasiswa' => $nama, 'nim' => '2201001', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
            'aksi' => $aksi,
        ]);
    }

    private function nomorHarapan(int $urut): string
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][now()->month - 1];

        return strtr(config('prodi.format_nomor'), [
            '{urut}' => str_pad((string) $urut, config('prodi.nomor_pad'), '0', STR_PAD_LEFT),
            '{prodi}' => config('prodi.kode_prodi'),
            '{fakultas}' => config('prodi.kode_fakultas'),
            '{bulan}' => $romawi,
            '{tahun}' => now()->year,
        ]);
    }

    public function test_format_nomor_mengikuti_contoh_surat_dinas(): void
    {
        $this->buatSurat();
        $surat = SuratKeluar::first();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat));

        $this->assertMatchesRegularExpression('#^1/TI-S1/FKOM-UNIKU/[IVX]+/\d{4}$#', $surat->fresh()->nomor_surat_resmi);
    }

    public function test_surat_dosen_tamu_merender_daftar_bernomor_dan_lampiran_default(): void
    {
        $jenis = JenisSurat::where('nama_surat', 'Permohonan Dana Kegiatan Dosen Tamu')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id,
            'tanggal_surat' => '2026-11-19',
            'tujuan_surat' => 'Dekan Fakultas Ilmu Komputer UNIKU',
            'perihal' => 'Permohonan Dana Kegiatan Dosen Tamu',
            'dinamis' => [
                'tanggal_kegiatan' => '10–11 Desember 2026',
                'narasumber' => "Prof. Dr. A (Riset Teknologi Informasi)\n\nProf. Dr. B (Rekayasa Perangkat Lunak)",
                'hari_tanggal' => 'Kamis–Jumat, 10–11 Desember 2026',
                'waktu' => '08.00 WIB - Selesai',
                'tempat' => 'Zoom Meeting',
            ],
            'aksi' => 'ajukan',
        ])->assertSessionHasNoErrors();

        $surat = SuratKeluar::first();
        $this->assertSame('1 Bundel', $surat->data_dinamis['lampiran']);
        $this->assertSame('Mahasiswa Program Studi Teknik Informatika S1', $surat->data_dinamis['peserta']);

        $html = \App\Services\SuratRenderer::isi($surat);
        $this->assertSame(2, substr_count($html, '<li>'));
        $this->assertStringContainsString('Kepada Yth.', $html);
        $this->assertStringContainsString('1 Bundel', $html);
    }

    /** Gambar uji: goresan gelap di atas latar putih (seperti foto tanda tangan). */
    private function gambarTandaTangan(): string
    {
        $im = imagecreatetruecolor(400, 160);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagesetthickness($im, 5);
        imageline($im, 60, 110, 200, 40, imagecolorallocate($im, 10, 10, 40));
        imageline($im, 200, 40, 340, 100, imagecolorallocate($im, 10, 10, 40));
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    public function test_kaprodi_mengunggah_tanda_tangan_dan_latar_putih_jadi_transparan(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('ttd.png', $this->gambarTandaTangan());

        $this->actingAs($this->kaprodi)->post(route('profile.tanda-tangan'), ['tanda_tangan' => $file])->assertSessionHasNoErrors();

        $path = $this->kaprodi->fresh()->signature_path;
        Storage::disk('public')->assertExists($path);

        $im = imagecreatefromstring(Storage::disk('public')->get($path));
        $this->assertLessThan(400, imagesx($im));
        $this->assertLessThan(160, imagesy($im));
        $this->assertGreaterThan(100, (imagecolorat($im, 0, 0) >> 24) & 127, 'sudut kosong harus transparan');

        $this->actingAs($this->kaprodi)->delete(route('profile.tanda-tangan.hapus'));
        $this->assertNull($this->kaprodi->fresh()->signature_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_tanda_tangan_di_profil_tampil_tanpa_url_publik(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('ttd.png', $this->gambarTandaTangan());
        $this->actingAs($this->kaprodi)->post(route('profile.tanda-tangan'), ['tanda_tangan' => $file]);
        $path = $this->kaprodi->fresh()->signature_path;

        $this->actingAs($this->kaprodi->fresh())->get(route('profile.edit'))->assertOk()
            ->assertSee('src="data:image/png;base64,'.base64_encode(Storage::disk('public')->get($path)).'"', false)
            ->assertDontSee($path, false);

        // Berkas hilang (mis. database dipindah tanpa folder storage) diberi keterangan, bukan gambar rusak.
        Storage::disk('public')->delete($path);
        $this->actingAs($this->kaprodi->fresh())->get(route('profile.edit'))->assertOk()
            ->assertSee('Berkas tanda tangan tidak ditemukan')
            ->assertDontSee('alt="Tanda tangan tersimpan"', false);
    }

    public function test_tanda_tangan_digambar_di_layar_dan_tampil_di_pdf(): void
    {
        Storage::fake('public');
        $url = 'data:image/png;base64,'.base64_encode($this->gambarTandaTangan());
        $this->actingAs($this->kaprodi)->post(route('profile.tanda-tangan'), ['tanda_tangan_gambar' => $url])->assertSessionHasNoErrors();
        $this->assertNotNull($this->kaprodi->fresh()->signature_path);

        $this->buatSurat();
        $surat = SuratKeluar::first();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat));
        $this->actingAs($this->staff)->get(route('surat-keluar.show', $surat))->assertOk()->assertSee('data:image/png;base64', false);
        $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $surat))->assertOk();
    }

    public function test_tanda_tangan_ditolak_untuk_staff_dan_berkas_bukan_gambar(): void
    {
        $this->actingAs($this->staff)->post(route('profile.tanda-tangan'), ['tanda_tangan_gambar' => 'x'])->assertForbidden();
        $this->actingAs($this->kaprodi)->post(route('profile.tanda-tangan'), ['tanda_tangan' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('tanda_tangan');
        $this->actingAs($this->kaprodi)->post(route('profile.tanda-tangan'), [])->assertSessionHasErrors('tanda_tangan');
        $kosong = 'data:image/png;base64,'.base64_encode((function () {
            $im = imagecreatetruecolor(100, 50);
            imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
            ob_start();
            imagepng($im);

            return ob_get_clean();
        })());
        $this->actingAs($this->kaprodi)->post(route('profile.tanda-tangan'), ['tanda_tangan_gambar' => $kosong])->assertSessionHasErrors('tanda_tangan');
    }

    public function test_nomor_perkiraan_tampil_saat_menyusun_dan_mengikuti_surat_terbit(): void
    {
        // Form menampilkan nomor berikutnya (urut 1) sebelum ada surat terbit.
        $this->actingAs($this->staff)->get(route('surat-keluar.create'))->assertOk()
            ->assertSee('Nomor surat (perkiraan)')->assertSee($this->nomorHarapan(1));

        $this->buatSurat('simpan');
        $draf = SuratKeluar::first();
        $this->assertNull($draf->nomor_surat_resmi, 'draf belum memakai nomor');
        $this->actingAs($this->staff)->get(route('surat-keluar.show', $draf))->assertSee($this->nomorHarapan(1))->assertSee('(perkiraan)');

        // Setelah satu surat terbit, perkiraan untuk surat berikutnya maju ke urut 2.
        $this->buatSurat();
        $terbit = SuratKeluar::where('status', 'menunggu_approval')->first();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $terbit));
        $this->assertSame($this->nomorHarapan(1), $terbit->fresh()->nomor_surat_resmi);

        $this->actingAs($this->staff)->get(route('surat-keluar.show', $draf))->assertSee($this->nomorHarapan(2));
        $this->actingAs($this->staff)->get(route('surat-keluar.edit', $draf))->assertOk()->assertSee($this->nomorHarapan(2));
    }

    public function test_registrasi_publik_tidak_ada(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_halaman_terproteksi_dan_role(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($this->kaprodi)->get(route('jenis-surat.index'))->assertForbidden();
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.create'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('dashboard'))->assertOk();
    }

    public function test_semua_halaman_dirender_tanpa_error(): void
    {
        $this->buatSurat('simpan');
        $surat = SuratKeluar::first();
        $jenis = JenisSurat::first();

        foreach ([
            route('surat-keluar.index'), route('surat-keluar.create'), route('surat-keluar.edit', $surat),
            route('surat-keluar.show', $surat), route('jenis-surat.index'), route('jenis-surat.create'),
            route('jenis-surat.edit', $jenis), route('surat-masuk.index'), route('surat-masuk.create'),
            route('pencarian', ['q' => 'a']), route('profile.edit'),
        ] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
        $this->actingAs($this->kaprodi)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->kaprodi)->get(route('profile.edit'))->assertOk();
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.index'))->assertOk();
    }

    public function test_alur_lengkap_draf_ajukan_revisi_terbit_pdf_validasi(): void
    {
        $this->buatSurat('simpan')->assertRedirect();
        $surat = SuratKeluar::first();
        $this->assertSame('draft', $surat->status);

        // Kaprodi tidak boleh melihat draf
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.show', $surat))->assertForbidden();

        $this->actingAs($this->staff)->post(route('surat-keluar.ajukan', $surat))->assertRedirect();
        $this->assertSame('menunggu_approval', $surat->fresh()->status);

        // Staf tidak boleh menyetujui; revisi butuh catatan
        $this->actingAs($this->staff)->post(route('surat-keluar.setujui', $surat))->assertForbidden();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.revisi', $surat), [])->assertSessionHasErrors('catatan_revisi');

        $this->actingAs($this->kaprodi)->post(route('surat-keluar.revisi', $surat), ['catatan_revisi' => 'Perbaiki nama instansi'])->assertRedirect();
        $this->assertSame('revisi', $surat->fresh()->status);
        $this->actingAs($this->staff)->get(route('surat-keluar.edit', $surat))->assertOk();

        $this->actingAs($this->staff)->post(route('surat-keluar.ajukan', $surat));
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat))->assertRedirect();

        $surat = $surat->fresh();
        $this->assertSame('terbit', $surat->status);
        $this->assertSame(1, $surat->nomor_urut);
        $this->assertSame($this->nomorHarapan(1), $surat->nomor_surat_resmi);

        // Terbit = terkunci
        $this->actingAs($this->staff)->get(route('surat-keluar.edit', $surat))->assertForbidden();
        $this->actingAs($this->staff)->put(route('surat-keluar.update', $surat), [])->assertForbidden();

        $res = $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $surat));
        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $res->assertHeader('content-type', 'application/pdf');

        // Validasi publik tanpa login
        auth()->logout();
        $this->get(route('validasi', $surat->uuid_validasi))->assertOk()->assertSee('DOKUMEN SAH')->assertSee($surat->nomor_surat_resmi);
        $this->get(route('validasi', '00000000-0000-0000-0000-000000000000'))->assertNotFound()->assertSee('TIDAK DITEMUKAN');
    }

    public function test_nomor_urut_berurutan_dan_reset_per_tahun(): void
    {
        $this->buatSurat();
        $this->buatSurat();
        SuratKeluar::query()->each(fn ($s) => $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s)));

        $this->assertEquals([1, 2], SuratKeluar::orderBy('nomor_urut')->pluck('nomor_urut')->all());

        // Simulasikan tahun berikutnya: nomor kembali ke 001
        $this->travelTo(now()->addYear());
        $this->buatSurat();
        $baru = SuratKeluar::whereNull('nomor_urut')->first();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $baru));
        $this->assertSame(1, $baru->fresh()->nomor_urut);
        $this->assertSame(now()->year, $baru->fresh()->tahun_terbit);
    }

    public function test_tidak_bisa_terbitkan_dua_kali(): void
    {
        $this->buatSurat();
        $s = SuratKeluar::first();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s));
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s))->assertSessionHasErrors('aksi');
        $this->assertSame(1, SuratKeluar::whereNotNull('nomor_urut')->count());
    }

    public function test_validasi_form_dinamis_dan_pencarian_nama(): void
    {
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-11-20', 'tujuan_surat' => 'X', 'perihal' => 'Y', 'dinamis' => [],
        ])->assertSessionHasErrors(['dinamis.nama_mahasiswa', 'dinamis.nim']);

        $this->buatSurat('ajukan', 'Siti Nurhaliza Ñandú');
        $this->actingAs($this->kaprodi)->get(route('pencarian', ['q' => 'Nurhaliza']))->assertOk()->assertSee('Permohonan Kerja Praktik');
        $this->actingAs($this->kaprodi)->get(route('pencarian', ['q' => 'Ñandú']))->assertOk()->assertSee('Permohonan Kerja Praktik');
        $this->actingAs($this->kaprodi)->get(route('pencarian', ['q' => 'tidakada']))->assertOk()->assertDontSee('Permohonan Kerja Praktik');
    }

    public function test_pratinjau_mengescape_html_pada_isian(): void
    {
        $this->buatSurat('ajukan', '<script>alert(1)</script>');
        $s = SuratKeluar::first();
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.show', $s))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_surat_masuk_arsip_nomor_agenda_dan_berkas_terproteksi(): void
    {
        Storage::fake('public');
        $payload = fn () => [
            'nomor_surat_asal' => '123/REK/2026', 'tanggal_surat' => '2026-11-01', 'tanggal_diterima' => '2026-11-05',
            'pengirim' => 'Rektorat', 'perihal' => 'Edaran libur', 'kategori' => 'Rektorat',
            'file_scan' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ];

        $this->actingAs($this->staff)->post(route('surat-masuk.store'), $payload())->assertRedirect();
        $this->actingAs($this->staff)->post(route('surat-masuk.store'), $payload());
        $this->assertEquals(['SM-2026-001', 'SM-2026-002'], \App\Models\SuratMasuk::orderBy('id')->pluck('nomor_agenda')->all());

        $this->actingAs($this->staff)->post(route('surat-masuk.store'), ['file_scan' => UploadedFile::fake()->create('x.exe', 10)] + $payload())
            ->assertSessionHasErrors('file_scan');

        $sm = \App\Models\SuratMasuk::first();
        Storage::disk('public')->assertExists($sm->file_scan_path);
        $this->actingAs($this->kaprodi)->get(route('surat-masuk.berkas', $sm))->assertOk();
        $this->actingAs($this->kaprodi)->post(route('surat-masuk.store'), $payload())->assertForbidden();

        auth()->logout();
        $this->get(route('surat-masuk.berkas', $sm))->assertRedirect('/login');
    }

    public function test_ekspor_csv(): void
    {
        $this->buatSurat();
        $s = SuratKeluar::first();
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s));

        $csv = $this->actingAs($this->staff)->get(route('ekspor.surat-keluar'))->streamedContent();
        $this->assertStringContainsString('Nomor Surat', $csv);
        $this->assertStringContainsString($s->fresh()->nomor_surat_resmi, $csv);
        $this->actingAs($this->staff)->get(route('ekspor.surat-masuk'))->assertOk();
    }

    public function test_jenis_surat_crud_dan_validasi_skema(): void
    {
        $this->actingAs($this->staff)->post(route('jenis-surat.store'), [
            'nama_surat' => 'Keterangan Akademik', 'template_html' => '<p>{{perihal}}</p>',
            'form_schema' => 'bukan json',
        ])->assertSessionHasErrors('form_schema');

        $this->actingAs($this->staff)->post(route('jenis-surat.store'), [
            'nama_surat' => 'Keterangan Akademik', 'template_html' => '<p>{{perihal}}</p>',
            'form_schema' => json_encode([['name' => 'nim', 'label' => 'NIM', 'type' => 'text', 'required' => true]]),
            'is_active' => '1',
        ])->assertRedirect(route('jenis-surat.index'));
        $this->assertDatabaseHas('jenis_surats', ['nama_surat' => 'Keterangan Akademik']);

        $this->buatSurat();
        $pkl = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->delete(route('jenis-surat.destroy', $pkl))->assertSessionHasErrors('hapus');
    }
}
