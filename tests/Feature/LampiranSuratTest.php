<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Services\SuratRenderer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LampiranSuratTest extends TestCase
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

    private function buat(array $dinamis, string $tujuan = "Dekan Fakultas Ilmu Komputer\nUniversitas Kuningan")
    {
        $jenis = JenisSurat::where('nama_surat', 'like', 'Surat Dinas Prodi%')->first();

        return $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-09-23', 'tujuan_surat' => $tujuan,
            'perihal' => 'Pengantar Dosen Pembuat Modul Praktikum',
            'dinamis' => ['isi_surat' => '<p class="indent">Bersama ini kami sampaikan daftar dosen.</p>'] + $dinamis,
            'aksi' => 'ajukan',
        ]);
    }

    private function jumlahHalamanPdf(SuratKeluar $s): int
    {
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $s));
        $pdf = $this->actingAs($this->staff)->get(route('surat-keluar.pdf', $s->fresh()))->assertOk()->getContent();

        return preg_match_all('/\/Type\s*\/Page\b/', $pdf);
    }

    public function test_tujuan_bertingkat_baris_pertama_tebal(): void
    {
        $this->buat([])->assertSessionHasNoErrors();

        $html = SuratRenderer::isi(SuratKeluar::first());
        $this->assertStringContainsString('<strong>Dekan Fakultas Ilmu Komputer</strong><br>Universitas Kuningan', $html);
        $this->assertStringContainsString('Kepada Yth.', $html);
    }

    public function test_tabel_lampiran_dirapikan_dan_dirender_di_halaman_terpisah(): void
    {
        $this->buat([
            'judul_lampiran' => 'Lampiran Daftar Dosen Pembuat Modul Praktikum',
            'tabel_lampiran' => [
                'kolom' => ['Nama', 'Modul Praktikum'],
                'baris' => [
                    ['Fauziah, M.Kom', 'Prak Pemrograman Fundamental'],
                    ['', ''],
                    ['Rudi Hidayat, M.Kom', 'Praktikum Algoritma dan Pemrograman'],
                    ['Hanya Satu Sel'],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $surat = SuratKeluar::first();
        $tabel = $surat->data_dinamis['tabel_lampiran'];
        $this->assertCount(3, $tabel['baris'], 'baris kosong dibuang');
        $this->assertSame(['Hanya Satu Sel', ''], $tabel['baris'][2], 'baris pendek dilengkapi');

        $bagian = SuratRenderer::bagian($surat);
        $this->assertStringNotContainsString('<table', $bagian['isi'] === '' ? '' : str_replace('<table style="width:100%; margin-bottom:12px">', '', $bagian['isi']), 'tabel tidak bocor ke isi surat');
        $this->assertStringContainsString('Lampiran Daftar Dosen Pembuat Modul Praktikum', $bagian['lampiran']);
        $this->assertStringContainsString('<th style=', $bagian['lampiran']);
        $this->assertStringContainsString('Modul Praktikum', $bagian['lampiran']);
        $this->assertStringContainsString('Rudi Hidayat, M.Kom', $bagian['lampiran']);
        $this->assertStringContainsString('>3</td>', $bagian['lampiran'], 'nomor urut otomatis');

        $this->actingAs($this->kaprodi)->get(route('surat-keluar.show', $surat))->assertOk()->assertSee('Halaman lampiran')->assertSee('Fauziah, M.Kom');
    }

    public function test_lampiran_kosong_tidak_dicetak(): void
    {
        $this->buat([])->assertSessionHasNoErrors();
        $surat = SuratKeluar::first();

        $this->assertSame('', SuratRenderer::lampiran($surat));
        $this->actingAs($this->kaprodi)->get(route('surat-keluar.show', $surat))->assertDontSee('Halaman lampiran');
    }

    public function test_pdf_memuat_halaman_kedua_hanya_bila_ada_lampiran(): void
    {
        $this->buat([])->assertSessionHasNoErrors();
        $tanpa = SuratKeluar::first();
        $this->assertSame(1, $this->jumlahHalamanPdf($tanpa));

        $this->buat([
            'judul_lampiran' => 'Lampiran',
            'tabel_lampiran' => ['kolom' => ['Nama', 'NIM'], 'baris' => [['Lingga Muhammad Asayu', '20240810057']]],
        ])->assertSessionHasNoErrors();
        $dengan = SuratKeluar::orderByDesc('id')->first();
        $this->assertSame(2, $this->jumlahHalamanPdf($dengan));
    }

    public function test_validasi_tabel(): void
    {
        $kolomTerlaluBanyak = array_map(fn ($i) => "K$i", range(1, 9));
        $this->buat(['tabel_lampiran' => ['kolom' => $kolomTerlaluBanyak, 'baris' => [array_fill(0, 9, 'x')]]])
            ->assertSessionHasErrors('dinamis.tabel_lampiran.kolom');

        $this->buat(['tabel_lampiran' => 'bukan tabel'])->assertSessionHasErrors('dinamis.tabel_lampiran');
        $this->assertSame(0, SuratKeluar::count());
    }

    public function test_tabel_besar_dikirim_sebagai_satu_json(): void
    {
        $baris = array_map(fn ($i) => ["Mahasiswa $i", "2024$i", 'A', 'B', 'C', 'D', 'E', "ujung $i"], range(1, 300));

        $this->buat(['judul_lampiran' => 'Daftar', 'tabel_lampiran' => json_encode([
            'kolom' => ['Nama', 'NIM', 'K3', 'K4', 'K5', 'K6', 'K7', 'K8'], 'baris' => $baris,
        ])])->assertSessionHasNoErrors();

        $tabel = SuratKeluar::first()->data_dinamis['tabel_lampiran'];
        $this->assertCount(300, $tabel['baris']);
        $this->assertSame('ujung 300', $tabel['baris'][299][7]);
    }

    public function test_json_tabel_tetap_divalidasi(): void
    {
        $this->buat(['tabel_lampiran' => '{rusak'])->assertSessionHasErrors('dinamis.tabel_lampiran');
        $this->buat(['tabel_lampiran' => json_encode(['kolom' => ['A'], 'baris' => array_fill(0, 301, ['x'])])])
            ->assertSessionHasErrors('dinamis.tabel_lampiran.baris');
        $this->buat(['tabel_lampiran' => json_encode(['kolom' => ['A'], 'baris' => [[['bersarang']]]])])
            ->assertSessionHasErrors('dinamis.tabel_lampiran.baris.0.0');
        $this->assertSame(0, SuratKeluar::count());
    }

    public function test_tabel_wajib_menolak_tabel_kosong(): void
    {
        $jenis = JenisSurat::create([
            'nama_surat' => 'Uji Tabel Wajib', 'template_html' => '{{tabel_wajib}}', 'is_active' => true,
            'form_schema' => [['name' => 'tabel_wajib', 'label' => 'Daftar Peserta', 'type' => 'tabel', 'required' => true, 'kolom' => ['Nama']]],
        ]);

        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id, 'tanggal_surat' => '2026-09-23', 'tujuan_surat' => 'X', 'perihal' => 'Y',
            'dinamis' => ['tabel_wajib' => ['kolom' => ['Nama'], 'baris' => [['']]]],
        ])->assertSessionHasErrors('dinamis.tabel_wajib');
    }

    public function test_pembuat_template_menyimpan_isian_tabel_dan_menolak_nama_terlarang(): void
    {
        $dasar = ['nama_surat' => 'Surat Uji Lampiran', 'is_active' => '1', 'template_html' => '<p>Isi</p>{{halaman_baru}}{{daftar_peserta}}'];

        $this->actingAs($this->staff)->post(route('jenis-surat.store'), $dasar + [
            'form_schema' => json_encode([['name' => 'daftar_peserta', 'label' => 'Daftar Peserta', 'type' => 'tabel', 'required' => false, 'kolom' => ['Nama', 'NIM']]]),
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Nama', 'NIM'], JenisSurat::where('nama_surat', 'Surat Uji Lampiran')->first()->form_schema[0]['kolom']);
        $this->assertStringContainsString('{{halaman_baru}}', JenisSurat::where('nama_surat', 'Surat Uji Lampiran')->first()->template_html, 'penanda lolos pembersih HTML');

        $this->actingAs($this->staff)->post(route('jenis-surat.store'), ['nama_surat' => 'Tanpa Kolom'] + $dasar + [
            'form_schema' => json_encode([['name' => 'daftar_peserta', 'label' => 'Daftar', 'type' => 'tabel']]),
        ])->assertSessionHasErrors('form_schema');

        $this->actingAs($this->staff)->post(route('jenis-surat.store'), ['nama_surat' => 'Nama Terlarang'] + $dasar + [
            'form_schema' => json_encode([['name' => 'halaman_baru', 'label' => 'Halaman', 'type' => 'text']]),
        ])->assertSessionHasErrors('form_schema');
    }

    public function test_form_membuat_surat_memuat_penyunting_tabel(): void
    {
        $this->actingAs($this->staff)->get(route('surat-keluar.create'))->assertOk()->assertSee('imporTabel', false)->assertSee('Unggah berkas .xlsx atau .csv')->assertSee('Halaman lampiran');
    }
}
