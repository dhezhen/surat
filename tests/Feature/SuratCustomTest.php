<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Services\SuratRenderer;
use App\Support\HtmlSanitizer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuratCustomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sanitizer_membuang_skrip_dan_mempertahankan_format_surat(): void
    {
        $kotor = '<p class="indent" style="text-align: justify; background:url(javascript:x)" onclick="x()">Halo <strong>tebal</strong><script>alert(1)</script></p>'
            .'<table style="width: 100%"><tbody><tr><td style="width: 2.4cm">Nomor</td><td>{{nomor_surat}}</td></tr></tbody></table>'
            .'<a href="javascript:alert(1)">klik</a><img src=x onerror=alert(1)><iframe src="x"></iframe>'
            .'<ol><li>satu</li></ol><p class="evil">x</p>';

        $bersih = HtmlSanitizer::bersihkan($kotor);

        $this->assertStringNotContainsString('script', $bersih);
        $this->assertStringNotContainsString('onclick', $bersih);
        $this->assertStringNotContainsString('javascript', $bersih);
        $this->assertStringNotContainsString('onerror', $bersih);
        $this->assertStringNotContainsString('iframe', $bersih);
        $this->assertStringNotContainsString('background', $bersih);
        $this->assertStringNotContainsString('class="evil"', $bersih);

        $this->assertStringContainsString('class="indent"', $bersih);
        $this->assertStringContainsString('text-align: justify', $bersih);
        $this->assertStringContainsString('<strong>tebal</strong>', $bersih);
        $this->assertStringContainsString('<table style="width: 100%">', $bersih);
        $this->assertStringContainsString('{{nomor_surat}}', $bersih);
        $this->assertStringContainsString('<ol><li>satu</li></ol>', $bersih);
        $this->assertStringContainsString('klik', $bersih, 'teks tautan dipertahankan, tag-nya dilepas');
    }

    public function test_surat_bebas_menyimpan_isi_berformat_dan_membersihkannya(): void
    {
        $staff = User::where('role', 'staff_prodi')->first();
        $jenis = JenisSurat::where('nama_surat', 'Surat Baru (Isi Saja)')->first();

        $this->actingAs($staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id,
            'tanggal_surat' => '2026-11-20',
            'tujuan_surat' => 'Bapak/Ibu Dosen',
            'perihal' => 'Pemberitahuan',
            'dinamis' => ['isi_surat' => '<p class="indent">Dengan hormat, <strong>harap hadir</strong>.</p><script>alert(1)</script>'],
            'aksi' => 'ajukan',
        ])->assertSessionHasNoErrors();

        $surat = SuratKeluar::first();
        $this->assertStringNotContainsString('script', $surat->data_dinamis['isi_surat']);
        $this->assertSame('-', $surat->data_dinamis['lampiran']);

        $html = SuratRenderer::isi($surat);
        $this->assertStringContainsString('<strong>harap hadir</strong>', $html);
        $this->assertStringContainsString('Kepada Yth.', $html);
        $this->assertStringContainsString('Dengan hormat,', $html);
        $this->assertStringContainsString('Demikian surat ini kami sampaikan.', $html);
        $this->assertStringContainsString('Bapak/Ibu Dosen', $html, 'tujuan surat masuk ke blok Kepada Yth.');

        $kaprodi = User::where('role', 'kaprodi')->first();
        $this->actingAs($kaprodi)->get(route('surat-keluar.show', $surat))->assertOk()->assertSee('harap hadir')->assertDontSee('alert(1)', false);
    }

    public function test_surat_bebas_wajib_ada_isi(): void
    {
        $staff = User::where('role', 'staff_prodi')->first();
        $jenis = JenisSurat::where('nama_surat', 'Surat Baru (Isi Saja)')->first();

        $this->actingAs($staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-11-20', 'tujuan_surat' => 'X', 'perihal' => 'Y', 'dinamis' => ['isi_surat' => ''],
        ])->assertSessionHasErrors('dinamis.isi_surat');
    }

    public function test_pembuat_template_visual_menyimpan_skema_dan_template_bersih(): void
    {
        $staff = User::where('role', 'staff_prodi')->first();

        $this->actingAs($staff)->post(route('jenis-surat.store'), [
            'nama_surat' => 'Keterangan Aktif Kuliah',
            'is_active' => '1',
            'form_schema' => json_encode([
                ['name' => 'nama_mahasiswa', 'label' => 'Nama Mahasiswa', 'type' => 'text', 'required' => true],
                ['name' => 'semester', 'label' => 'Semester', 'type' => 'select', 'required' => true, 'options' => ['Ganjil', 'Genap']],
                ['name' => 'uraian', 'label' => 'Uraian', 'type' => 'richtext', 'required' => false, 'default' => '-'],
            ]),
            'template_html' => '<p class="indent">Mahasiswa {{nama_mahasiswa}} aktif pada semester {{semester}}.</p><script>x()</script>{{uraian}}',
        ])->assertSessionHasNoErrors()->assertRedirect(route('jenis-surat.index'));

        $jenis = JenisSurat::where('nama_surat', 'Keterangan Aktif Kuliah')->first();
        $this->assertStringNotContainsString('script', $jenis->template_html);
        $this->assertStringContainsString('{{nama_mahasiswa}}', $jenis->template_html);
        $this->assertCount(3, $jenis->form_schema);

        $this->actingAs($staff)->get(route('jenis-surat.edit', $jenis))->assertOk()->assertSee('Nama Mahasiswa');
        $this->actingAs($staff)->get(route('surat-keluar.create'))->assertOk()->assertSee('Keterangan Aktif Kuliah');
    }

    public function test_semua_penanda_di_template_bawaan_punya_isian(): void
    {
        $bawaan = ['nomor_surat', 'tanggal_surat', 'tujuan_surat', 'perihal', 'halaman_baru'];

        foreach (JenisSurat::all() as $jenis) {
            $tersedia = $bawaan;
            foreach ($jenis->form_schema as $f) {
                $tersedia[] = $f['name'];
                if ($f['type'] === 'date') {
                    $tersedia[] = $f['name'].'_lengkap';
                }
            }

            preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/', $jenis->template_html, $m);
            $this->assertSame([], array_values(array_diff($m[1], $tersedia)), "Penanda tak dikenal pada template '{$jenis->nama_surat}'");
            $setelahBersih = HtmlSanitizer::bersihkan($jenis->template_html);
            $this->assertSame(substr_count($jenis->template_html, '{{'), substr_count($setelahBersih, '{{'), "Pembersih HTML menghilangkan penanda pada '{$jenis->nama_surat}'");
        }
    }

    public function test_undangan_rapat_menampilkan_nama_hari(): void
    {
        $staff = User::where('role', 'staff_prodi')->first();
        $jenis = JenisSurat::where('nama_surat', 'Undangan Rapat')->first();

        $this->actingAs($staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-07-29', 'tujuan_surat' => 'Ketua dan Anggota Gugus Mutu Kendali',
            'perihal' => 'Undangan Rapat GKM',
            'dinamis' => ['hari_tanggal' => '2026-07-30', 'tempat' => 'Ruang Program Studi Teknik Informatika'],
        ])->assertSessionHasNoErrors();

        $html = SuratRenderer::isi(SuratKeluar::first());
        $this->assertStringContainsString('Kamis, 30 Juli 2026', $html);
        $this->assertStringContainsString('Pukul 09.00 WIB s.d. selesai', $html, 'nilai awal waktu terpakai');
        $this->assertStringContainsString('Wassalamu', $html);
    }

    public function test_template_pertanggungjawaban_dan_akomodasi_merender_isian(): void
    {
        $staff = User::where('role', 'staff_prodi')->first();

        $spj = JenisSurat::where('nama_surat', 'like', 'Surat Pengantar Pertanggungjawaban%')->first();
        $this->actingAs($staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $spj->id, 'tanggal_surat' => '2026-11-10', 'tujuan_surat' => 'Dekan Fakultas Ilmu Komputer',
            'perihal' => 'Pengantar LPJ Keuangan Konferensi',
            'dinamis' => ['kegiatan' => 'konferensi internasional INCITEST 2026', 'jenis_laporan' => 'Laporan Pertanggungjawaban (LPJ)'],
        ])->assertSessionHasNoErrors();

        $akomodasi = JenisSurat::where('nama_surat', 'Permohonan Akomodasi Keikutsertaan Kegiatan')->first();
        $this->actingAs($staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $akomodasi->id, 'tanggal_surat' => '2026-11-09', 'tujuan_surat' => 'Dekan Fakultas Ilmu Komputer',
            'perihal' => 'Permohonan Fasilitasi Akomodasi',
            'dinamis' => ['latar' => 'Sehubungan dengan lolosnya Tim ANANTA AI sebagai peserta SENO Hackathon ITS 2026,',
                'hari_tanggal' => 'Senin–Kamis, 9–12 November 2026', 'tempat' => 'Hall STP Creative ITS, Surabaya',
                'peserta' => "Mahasiswa A\nMahasiswa B"],
        ])->assertSessionHasNoErrors();

        [$a, $b] = SuratKeluar::orderBy('id')->get()->all();
        $htmlA = SuratRenderer::isi($a);
        $this->assertStringContainsString('konferensi internasional INCITEST 2026', $htmlA);
        $this->assertStringContainsString('Demikian Laporan Pertanggungjawaban (LPJ) ini', $htmlA);
        $this->assertStringContainsString('Terlampir rincian keuangan', $htmlA);

        $htmlB = SuratRenderer::isi($b);
        $this->assertSame(2, substr_count($htmlB, '<li>'));
        $this->assertStringContainsString('Hall STP Creative ITS, Surabaya', $htmlB);
        $this->assertStringContainsString('memfasilitasi kebutuhan transportasi', $htmlB);
    }

    public function test_surat_dinas_umum_memakai_penutup_bawaan(): void
    {
        $staff = User::where('role', 'staff_prodi')->first();
        $jenis = JenisSurat::where('nama_surat', 'like', 'Surat Dinas Prodi%')->first();

        $this->actingAs($staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-11-02', 'tujuan_surat' => 'Dekan Fakultas Ilmu Komputer',
            'perihal' => 'Surat Pengantar Peserta Sidang',
            'dinamis' => ['isi_surat' => '<p class="indent">Bersama ini kami sampaikan daftar peserta Sidang Skripsi.</p>'],
        ])->assertSessionHasNoErrors();

        $html = SuratRenderer::isi(SuratKeluar::first());
        $this->assertStringContainsString('Dengan hormat,', $html);
        $this->assertStringContainsString('daftar peserta Sidang Skripsi', $html);
        $this->assertStringContainsString('Demikian surat ini kami sampaikan.', $html);
    }

    public function test_nama_jenis_surat_harus_unik(): void
    {
        $staff = User::where('role', 'staff_prodi')->first();

        $this->actingAs($staff)->post(route('jenis-surat.store'), [
            'nama_surat' => 'Surat Baru (Isi Saja)', 'form_schema' => '[]', 'template_html' => '<p>x</p>',
        ])->assertSessionHasErrors('nama_surat');
    }
}
