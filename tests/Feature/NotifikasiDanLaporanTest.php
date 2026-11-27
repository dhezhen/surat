<?php

namespace Tests\Feature;

use App\Models\JenisSurat;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Notifications\PerubahanStatusSurat;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifikasiDanLaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
    }

    private function ajukanSurat(): SuratKeluar
    {
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id,
            'tanggal_surat' => now()->toDateString(),
            'tujuan_surat' => 'PT Contoh Teknologi',
            'perihal' => 'Permohonan Kerja Praktik',
            'dinamis' => ['nama_mahasiswa' => 'Budi', 'nim' => '2201001', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
            'aksi' => 'ajukan',
        ])->assertRedirect();

        return SuratKeluar::latest('id')->first();
    }

    public function test_pengajuan_memberi_tahu_kaprodi_lewat_aplikasi_dan_email(): void
    {
        Notification::fake();
        $surat = $this->ajukanSurat();

        Notification::assertSentTo($this->kaprodi, PerubahanStatusSurat::class,
            fn ($n, $kanal) => $n->peristiwa === 'diajukan' && $n->surat->is($surat) && $kanal === ['database']);
        Notification::assertSentTo($this->kaprodi, PerubahanStatusSurat::class, fn ($n, $kanal) => $kanal === ['mail']);
        Notification::assertNotSentTo($this->staff, PerubahanStatusSurat::class);
    }

    public function test_draf_tidak_memicu_notifikasi(): void
    {
        Notification::fake();
        $jenis = JenisSurat::where('nama_surat', 'Surat Pengantar Kerja Praktik')->first();
        $this->actingAs($this->staff)->post(route('surat-keluar.store'), [
            'jenis_surat_id' => $jenis->id,
            'tanggal_surat' => now()->toDateString(),
            'tujuan_surat' => 'PT Contoh',
            'perihal' => 'Draf saja',
            'dinamis' => ['nama_mahasiswa' => 'Budi', 'nim' => '1', 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2027-01-31'],
            'aksi' => 'simpan',
        ]);

        Notification::assertNothingSent();
    }

    public function test_persetujuan_dan_revisi_memberi_tahu_pembuat(): void
    {
        $surat = $this->ajukanSurat();
        Notification::fake();

        $this->actingAs($this->kaprodi)->post(route('surat-keluar.revisi', $surat), ['catatan_revisi' => 'Perbaiki NIM']);
        Notification::assertSentTo($this->staff, PerubahanStatusSurat::class,
            fn ($n, $kanal) => $n->peristiwa === 'revisi' && $n->toArray($this->staff)['catatan'] === 'Perbaiki NIM');

        $this->actingAs($this->staff)->post(route('surat-keluar.ajukan', $surat));
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat), ['metode_ttd' => 'qr']);
        Notification::assertSentTo($this->staff, PerubahanStatusSurat::class,
            fn ($n) => $n->peristiwa === 'disetujui' && str_contains($n->pesan(), $surat->fresh()->nomor_surat_resmi));
    }

    public function test_email_tidak_dikirim_bila_dimatikan_di_profil(): void
    {
        $this->actingAs($this->kaprodi)->patch(route('profile.update'), [
            'name' => $this->kaprodi->name,
            'jabatan' => $this->kaprodi->jabatan,
            'email' => $this->kaprodi->email,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->kaprodi->fresh()->notif_email);

        Notification::fake();
        $this->ajukanSurat();

        Notification::assertSentTo($this->kaprodi, PerubahanStatusSurat::class, fn ($n, $kanal) => $kanal === ['database']);
        Notification::assertNotSentTo($this->kaprodi, PerubahanStatusSurat::class, fn ($n, $kanal) => $kanal === ['mail']);
    }

    public function test_membuka_notifikasi_menandai_dibaca_dan_menuju_surat(): void
    {
        $surat = $this->ajukanSurat();
        $notifikasi = $this->kaprodi->unreadNotifications()->first();
        $this->assertNotNull($notifikasi);

        $this->actingAs($this->kaprodi)->get(route('notifikasi.index'))
            ->assertOk()->assertSee('Surat menunggu persetujuan');

        $this->actingAs($this->kaprodi)->get(route('notifikasi.buka', $notifikasi->id))
            ->assertRedirect(route('surat-keluar.show', $surat));
        $this->assertNotNull($notifikasi->fresh()->read_at);

        // Notifikasi milik orang lain tidak bisa dibuka.
        $this->actingAs($this->staff)->get(route('notifikasi.buka', $notifikasi->id))->assertNotFound();
    }

    public function test_tandai_semua_dibaca(): void
    {
        $this->ajukanSurat();
        $this->ajukanSurat();
        $this->assertSame(2, $this->kaprodi->unreadNotifications()->count());

        $this->actingAs($this->kaprodi)->post(route('notifikasi.baca-semua'))->assertRedirect();
        $this->assertSame(0, $this->kaprodi->unreadNotifications()->count());
    }

    public function test_laporan_merekap_surat_dan_lama_persetujuan(): void
    {
        Carbon::setTestNow('2026-03-10 08:00:00');
        $surat = $this->ajukanSurat();
        Carbon::setTestNow('2026-03-10 14:00:00');
        $this->actingAs($this->kaprodi)->post(route('surat-keluar.setujui', $surat), ['metode_ttd' => 'qr']);

        $surat->refresh();
        $this->assertSame('2026-03-10 08:00:00', $surat->diajukan_at->toDateTimeString());
        $this->assertSame('2026-03-10 14:00:00', $surat->disetujui_at->toDateTimeString());

        SuratMasuk::create([
            'nomor_agenda' => 'SM-TES-1', 'nomor_surat_asal' => '1/X/2026', 'tanggal_surat' => '2026-05-01',
            'tanggal_diterima' => '2026-05-02', 'pengirim' => 'Rektorat', 'perihal' => 'Undangan rapat',
            'kategori' => 'Undangan', 'file_scan_path' => 'tes.pdf',
        ]);

        $respons = $this->actingAs($this->staff)->get(route('laporan', ['tahun' => 2026]));
        $respons->assertOk()
            ->assertViewHas('totalKeluar', 1)
            ->assertViewHas('totalMasuk', fn ($n) => $n >= 1)
            ->assertViewHas('perBulanKeluar', fn ($b) => $b[2] === 1 && array_sum($b) === 1)
            ->assertViewHas('perJenis', fn ($j) => $j[0]['nama'] === 'Surat Pengantar Kerja Praktik')
            ->assertViewHas('persetujuan', fn ($p) => $p['jumlah'] === 1 && $p['rata'] === 6 * 3600)
            ->assertSee('6,0 jam');

        Carbon::setTestNow();
    }

    public function test_laporan_bisa_dibuka_semua_peran_dan_tahun_asing_diabaikan(): void
    {
        foreach (['staff_prodi', 'kaprodi', 'super_admin'] as $peran) {
            $this->actingAs(User::where('role', $peran)->first())
                ->get(route('laporan', ['tahun' => 1990]))
                ->assertOk()
                ->assertViewHas('tahun', now()->year);
        }
    }
}
