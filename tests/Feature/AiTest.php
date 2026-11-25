<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\User;
use App\Services\Ai\AiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'AIzaSyRAHASIA-1234567890abcdWXYZ';

    private User $admin;
    private User $staff;
    private User $kaprodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'ti@uniku.ac.id')->first();
        $this->staff = User::where('role', 'staff_prodi')->first();
        $this->kaprodi = User::where('role', 'kaprodi')->first();
    }

    private function aktifkan(string $penyedia = 'gemini', string $kunci = self::KUNCI): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), [
            'penyedia' => $penyedia, 'aktif' => '1', 'kunci' => [$penyedia => $kunci], 'url_ollama' => 'http://localhost:11434',
        ])->assertSessionHasNoErrors();
    }

    private function susun(array $ubah = [])
    {
        return $this->actingAs($this->staff)->postJson(route('ai.susun'), array_merge([
            'pokok' => 'mohon dana honor dua narasumber kuliah umum tanggal 10-11 Desember 2026',
            'perihal' => 'Permohonan Dana', 'tujuan' => 'Dekan', 'jenis' => 'Surat Baru (Isi Saja)',
        ], $ubah));
    }

    private mixed $balasan = null;
    private bool $httpDisiapkan = false;

    /** Satu stub HTTP yang balasannya bisa diganti di tengah tes (stub Http::fake berikutnya tidak menimpa yang pertama). */
    private function balas(mixed $respons): void
    {
        $this->balasan = $respons;
        if (! $this->httpDisiapkan) {
            Http::fake(fn () => $this->balasan);
            $this->httpDisiapkan = true;
        }
    }

    private function balasanGemini(string $teks): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $teks]]]]]];
    }

    public function test_hanya_super_admin_membuka_pengaturan_ai(): void
    {
        $this->actingAs($this->admin)->get(route('pengaturan.ai'))->assertOk()->assertSee('Gemini')->assertSee('Claude')->assertSee('OpenAI')->assertSee('Ollama');
        $this->actingAs($this->staff)->get(route('pengaturan.ai'))->assertForbidden();
        $this->actingAs($this->kaprodi)->get(route('pengaturan.ai'))->assertForbidden();
        $this->actingAs($this->staff)->put(route('pengaturan.ai.update'), ['penyedia' => 'gemini'])->assertForbidden();
    }

    public function test_kunci_api_disimpan_terenkripsi_dan_tidak_pernah_ditampilkan(): void
    {
        $this->aktifkan();

        $mentah = DB::table('pengaturans')->where('kunci', 'ai.gemini.kunci')->value('nilai');
        $this->assertNotEmpty($mentah);
        $this->assertStringNotContainsString('RAHASIA', $mentah, 'tersimpan terenkripsi, bukan teks biasa');
        $this->assertSame(self::KUNCI, Pengaturan::ambilRahasia('ai.gemini.kunci'));

        $halaman = $this->actingAs($this->admin)->get(route('pengaturan.ai'))->assertOk();
        $halaman->assertDontSee(self::KUNCI);
        $halaman->assertSee('WXYZ');
        $halaman->assertSee('Tersimpan terenkripsi');
    }

    public function test_mengosongkan_kolom_kunci_tidak_menghapus_dan_bisa_dihapus_eksplisit(): void
    {
        $this->aktifkan();

        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), ['penyedia' => 'gemini', 'aktif' => '1', 'kunci' => ['gemini' => '']])->assertSessionHasNoErrors();
        $this->assertSame(self::KUNCI, Pengaturan::ambilRahasia('ai.gemini.kunci'));

        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), ['penyedia' => 'gemini', 'hapus_kunci' => ['gemini' => '1']])->assertSessionHasNoErrors();
        $this->assertNull(Pengaturan::ambilRahasia('ai.gemini.kunci'));
        $this->assertFalse(AiService::tersedia());
    }

    public function test_tidak_bisa_mengaktifkan_tanpa_kunci(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), ['penyedia' => 'anthropic', 'aktif' => '1'])->assertSessionHasErrors('aktif');
        $this->assertFalse(AiService::tersedia());
        $this->assertSame('0', Pengaturan::ambil('ai.aktif'));
    }

    public function test_validasi_pengaturan(): void
    {
        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), ['penyedia' => 'tidak-ada'])->assertSessionHasErrors('penyedia');
        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), ['penyedia' => 'ollama', 'url_ollama' => 'javascript:alert(1)'])->assertSessionHasErrors('url_ollama');
        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), ['penyedia' => 'gemini', 'model' => ['gemini' => 'model; DROP TABLE']])->assertSessionHasErrors('model.gemini');
    }

    public function test_staff_menyusun_kalimat_lewat_gemini_dan_hasilnya_dibersihkan(): void
    {
        $this->aktifkan();
        $this->balas(Http::response($this->balasanGemini(
            "```html\n<p class=\"indent\">Dalam rangka kuliah umum, kami memohon dana honorarium.</p><script>alert(1)</script>\n```"
        )));

        $res = $this->susun()->assertOk();

        $this->assertStringContainsString('memohon dana honorarium', $res->json('html'));
        $this->assertStringNotContainsString('script', $res->json('html'));
        $this->assertStringNotContainsString('```', $res->json('html'));

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent')
                && $req->hasHeader('x-goog-api-key', self::KUNCI)
                && str_contains(json_encode($req->data()), 'honor dua narasumber')
                && str_contains(json_encode($req->data()), 'Permohonan Dana')
                && ! str_contains(json_encode($req->data()), 'staff@prodi.ac.id');
        });
    }

    public function test_penyedia_lain_memakai_endpoint_dan_format_masing_masing(): void
    {
        $this->aktifkan('anthropic', 'sk-ant-rahasia');
        $this->balas(Http::response(['content' => [['type' => 'text', 'text' => '<p>Dari Claude.</p>']]]));
        $this->assertStringContainsString('Dari Claude.', $this->susun()->assertOk()->json('html'));
        Http::assertSent(fn ($r) => $r->url() === 'https://api.anthropic.com/v1/messages' && $r->hasHeader('x-api-key', 'sk-ant-rahasia') && $r->hasHeader('anthropic-version'));

        $this->aktifkan('openai', 'sk-openai-rahasia');
        $this->balas(Http::response(['choices' => [['message' => ['content' => '<p>Dari OpenAI.</p>']]]]));
        $this->assertStringContainsString('Dari OpenAI.', $this->susun()->assertOk()->json('html'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openai.com/v1/chat/completions') && $r->hasHeader('Authorization', 'Bearer sk-openai-rahasia'));

        $this->aktifkan('openrouter', 'sk-or-rahasia');
        $this->balas(Http::response(['choices' => [['message' => ['content' => '<p>Dari OpenRouter.</p>']]]]));
        $this->assertStringContainsString('Dari OpenRouter.', $this->susun()->assertOk()->json('html'));
        Http::assertSent(fn ($r) => $r->url() === 'https://openrouter.ai/api/v1/chat/completions' && $r->hasHeader('Authorization', 'Bearer sk-or-rahasia') && $r['model'] === 'openrouter/free');

        $this->aktifkan('ollama');
        $this->balas(Http::response(['message' => ['content' => "Paragraf pertama.\n\nParagraf kedua."]]));
        $html = $this->susun()->assertOk()->json('html');
        $this->assertSame(2, substr_count($html, '<p class="indent">'), 'teks polos dijadikan paragraf');
        Http::assertSent(fn ($r) => $r->url() === 'http://localhost:11434/api/chat' && ! $r->hasHeader('Authorization'));
    }

    public function test_kegagalan_penyedia_diterjemahkan_tanpa_membocorkan_kunci(): void
    {
        $this->aktifkan();

        foreach ([401 => 'Kunci API ditolak', 404 => 'Model tidak ditemukan', 429 => 'Batas penggunaan', 500 => 'sedang bermasalah'] as $status => $potongan) {
            $this->balas(Http::response(['error' => ['message' => 'detail internal '.self::KUNCI]], $status));
            $res = $this->susun()->assertStatus(422);
            $this->assertStringContainsString($potongan, $res->json('message'));
            $this->assertStringNotContainsString('RAHASIA', $res->getContent());
        }

        $this->balas(Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]));
        $this->assertStringContainsString('filter keamanan', $this->susun()->assertStatus(422)->json('message'));

        $this->balas(Http::response($this->balasanGemini('   ')));
        $this->susun()->assertStatus(422);
    }

    public function test_susun_ditolak_bila_nonaktif_atau_bukan_staff_atau_input_kurang(): void
    {
        $this->susun()->assertStatus(403);

        $this->aktifkan();
        $this->balas(Http::response($this->balasanGemini('<p>x</p>')));

        $this->actingAs($this->kaprodi)->postJson(route('ai.susun'), ['pokok' => 'cukup panjang kalimatnya'])->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('ai.susun'), ['pokok' => 'cukup panjang kalimatnya'])->assertForbidden();
        $this->susun(['pokok' => 'pendek'])->assertStatus(422);
        $this->susun(['pokok' => ''])->assertStatus(422);
        Http::assertNothingSent();

        auth()->logout();
        $this->postJson(route('ai.susun'), ['pokok' => 'cukup panjang kalimatnya'])->assertUnauthorized();
    }

    public function test_tombol_ai_di_editor_hanya_muncul_bila_fitur_aktif(): void
    {
        $this->actingAs($this->staff)->get(route('surat-keluar.create'))->assertOk()->assertDontSee('Susun dengan AI');

        $this->aktifkan();
        $this->actingAs($this->staff)->get(route('surat-keluar.create'))->assertOk()->assertSee('Susun dengan AI')->assertDontSee(self::KUNCI);

        $this->actingAs($this->admin)->put(route('pengaturan.ai.update'), ['penyedia' => 'gemini']); // nonaktif
        $this->actingAs($this->staff)->get(route('surat-keluar.create'))->assertDontSee('Susun dengan AI');
    }

    public function test_uji_koneksi(): void
    {
        $this->aktifkan();

        $this->balas(Http::response($this->balasanGemini('OK')));
        $this->actingAs($this->admin)->post(route('pengaturan.ai.uji'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Terhubung') && str_contains($m, 'OK'));

        $this->balas(Http::response([], 401));
        $this->actingAs($this->admin)->post(route('pengaturan.ai.uji'))->assertSessionHasErrors('uji');

        $this->actingAs($this->staff)->post(route('pengaturan.ai.uji'))->assertForbidden();
    }

    public function test_kunci_tidak_terbaca_bila_app_key_berubah(): void
    {
        $this->aktifkan();
        Pengaturan::simpan('ai.gemini.kunci', 'bukan-hasil-enkripsi-valid');

        $this->assertNull(Pengaturan::ambilRahasia('ai.gemini.kunci'));
        $this->assertFalse(AiService::tersedia());
        $this->actingAs($this->admin)->get(route('pengaturan.ai'))->assertOk();
    }
}
