<?php

namespace App\Services\Ai;

use App\Models\Pengaturan;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AiService
{
    /** Daftar penyedia yang didukung. 'kunci' menandakan penyedia memerlukan kunci API. */
    public const PENYEDIA = [
        'gemini' => ['label' => 'Gemini (Google)', 'model' => 'gemini-3.5-flash-lite', 'kunci' => true, 'pilihan' => [
            'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite: ringan dan hemat',
            'gemini-3.8-flash' => 'Gemini 3.8 Flash: lebih cermat',
            'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
        ]],
        'anthropic' => ['label' => 'Claude (Anthropic)', 'model' => 'claude-sonnet-5-5', 'kunci' => true, 'pilihan' => [
            'claude-haiku-5-5' => 'Claude Haiku 5.5: cepat dan hemat',
            'claude-sonnet-5-5' => 'Claude Sonnet 5.5: seimbang',
            'claude-opus-5-5' => 'Claude Opus 5.5: lebih cermat',
            'claude-fable-5-2' => 'Claude Fable 5.2: paling mumpuni',
        ]],
        'openai' => ['label' => 'OpenAI', 'model' => 'gpt-4o-mini', 'kunci' => true, 'pilihan' => [
            'gpt-4o-mini' => 'GPT-4o mini: cepat dan hemat',
            'gpt-4o' => 'GPT-4o: seimbang',
            'gpt-4.1-mini' => 'GPT-4.1 mini',
            'gpt-4.1' => 'GPT-4.1: lebih cermat',
        ]],
        'openrouter' => ['label' => 'OpenRouter (banyak model, ada yang gratis)', 'model' => 'openrouter/free', 'kunci' => true, 'pilihan' => [
            'openrouter/free' => 'Otomatis pilih model gratis yang tersedia',
            'meta-llama/llama-3.3-70b-instruct:free' => 'Llama 3.3 70B (gratis)',
            'google/gemma-3-27b-it:free' => 'Gemma 3 27B (gratis)',
            'deepseek/deepseek-chat-v3-0324:free' => 'DeepSeek V3 (gratis)',
        ]],
        'ollama' => ['label' => 'Ollama (model lokal di komputer sendiri)', 'model' => 'llama3.1', 'kunci' => false, 'pilihan' => [
            'llama3.1' => 'Llama 3.1',
            'llama3.2' => 'Llama 3.2',
            'qwen2.5' => 'Qwen 2.5',
            'gemma2' => 'Gemma 2',
            'mistral' => 'Mistral',
        ]],
    ];

    private const PETUNJUK_SISTEM = <<<'TXT'
Anda adalah asisten penyusun surat dinas perguruan tinggi di Indonesia. Tulis dalam bahasa Indonesia baku (PUEBI) dengan gaya tata naskah dinas: sopan, lugas, dan ringkas.

Tugas: ubah pokok isi dari pengguna menjadi isi surat berupa beberapa paragraf.

Aturan:
- Tulis HANYA isi surat. Jangan menulis kop, nomor, tanggal, "Kepada Yth.", salam pembuka "Dengan hormat,", kalimat penutup "Demikian surat ini kami sampaikan...", nama, jabatan, atau tanda tangan, karena semuanya sudah tersedia otomatis.
- Jangan mengarang fakta. Jangan menambah nama, tanggal, angka, tempat, atau nomor yang tidak ada pada pokok isi. Jika ada data penting yang belum diberikan, tulis penanda seperti [ISI TANGGAL] agar pengguna melengkapinya.
- Gunakan "kami" untuk program studi dan "Bapak/Ibu" untuk penerima.
- Keluaran berupa HTML sederhana saja: <p class="indent"> untuk paragraf, <strong>, <em>, <ol>/<ul> dengan <li>. Tanpa Markdown, tanpa blok kode, tanpa komentar atau penjelasan di luar isi surat.
TXT;

    public static function penyediaAktif(): ?string
    {
        $p = Pengaturan::ambil('ai.penyedia');

        return isset(self::PENYEDIA[$p]) ? $p : null;
    }

    public static function model(string $penyedia): string
    {
        return Pengaturan::ambil("ai.$penyedia.model") ?: self::PENYEDIA[$penyedia]['model'];
    }

    public static function punyaKonfigurasi(string $penyedia): bool
    {
        if (! isset(self::PENYEDIA[$penyedia])) {
            return false;
        }

        return self::PENYEDIA[$penyedia]['kunci']
            ? Pengaturan::ambilRahasia("ai.$penyedia.kunci") !== null
            : (bool) Pengaturan::ambil("ai.$penyedia.url");
    }

    /** Fitur dianggap tersedia bagi staff hanya bila diaktifkan dan penyedia terpilih sudah dikonfigurasi. */
    public static function tersedia(): bool
    {
        $p = self::penyediaAktif();

        return Pengaturan::ambil('ai.aktif') === '1' && $p !== null && self::punyaKonfigurasi($p);
    }

    /**
     * @param  array{pokok: string, perihal?: ?string, tujuan?: ?string, jenis?: ?string}  $input
     * @return string HTML isi surat yang sudah dibersihkan
     */
    public function susun(array $input): string
    {
        $pesan = "Pokok isi surat:\n".trim($input['pokok']);
        foreach (['jenis' => 'Jenis surat', 'perihal' => 'Perihal', 'tujuan' => 'Tujuan surat'] as $k => $label) {
            if (! empty($input[$k])) {
                $pesan .= "\n{$label}: ".trim(preg_replace('/\s+/', ' ', $input[$k]));
            }
        }

        return $this->bersihkan($this->panggil($pesan, self::PETUNJUK_SISTEM, 1500));
    }

    /** Memeriksa koneksi dan kunci dengan permintaan sekecil mungkin. */
    public function uji(): string
    {
        $jawaban = $this->panggil('Balas hanya dengan satu kata: OK', 'Anda adalah penguji koneksi. Balas sesingkat mungkin.', 20);

        return trim(strip_tags($jawaban)) ?: '(balasan kosong)';
    }

    private function bersihkan(string $keluaran): string
    {
        // Sebagian model membungkus jawaban dengan blok kode Markdown.
        $keluaran = trim(preg_replace('/^```(?:html)?\s*|\s*```$/i', '', trim($keluaran)));
        $html = HtmlSanitizer::bersihkan($keluaran);

        if (trim(strip_tags($html)) === '') {
            throw new AiException('AI tidak menghasilkan isi surat. Coba perjelas pokok isinya.');
        }

        // Teks polos tanpa tag dijadikan paragraf.
        if (str_contains($html, '<')) {
            return $html;
        }

        $paragraf = array_filter(array_map('trim', preg_split('/\R{2,}/', html_entity_decode($html))));

        return implode('', array_map(fn ($p) => '<p class="indent">'.nl2br(e($p)).'</p>', $paragraf));
    }

    private function panggil(string $pesan, string $sistem, int $maksToken): string
    {
        $p = self::penyediaAktif();
        if (! $p || ! self::punyaKonfigurasi($p)) {
            throw new AiException('Penyedia AI belum dikonfigurasi. Hubungi Super Admin.');
        }

        $model = self::model($p);

        try {
            return match ($p) {
                'gemini' => $this->gemini($model, $pesan, $sistem, $maksToken),
                'anthropic' => $this->anthropic($model, $pesan, $sistem, $maksToken),
                'openai' => $this->openai('openai', 'https://api.openai.com/v1/chat/completions', $model, $pesan, $sistem, $maksToken),
                'openrouter' => $this->openai('openrouter', 'https://openrouter.ai/api/v1/chat/completions', $model, $pesan, $sistem, $maksToken),
                'ollama' => $this->ollama($model, $pesan, $sistem),
            };
        } catch (ConnectionException) {
            throw new AiException($p === 'ollama'
                ? 'Tidak dapat terhubung ke Ollama. Pastikan Ollama berjalan dan alamatnya benar.'
                : 'Tidak dapat menghubungi layanan AI. Periksa koneksi internet server.');
        }
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(60)->acceptJson();
    }

    private function gemini(string $model, string $pesan, string $sistem, int $maks): string
    {
        $r = $this->http()->withHeaders(['x-goog-api-key' => Pengaturan::ambilRahasia('ai.gemini.kunci')])
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $sistem]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $pesan]]]],
                'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => $maks],
            ]);
        $this->periksa($r);

        $teks = $r->json('candidates.0.content.parts.0.text');
        if ($teks === null) {
            $alasan = $r->json('promptFeedback.blockReason');
            throw new AiException($alasan ? 'Permintaan ditolak oleh filter keamanan Gemini.' : 'Gemini tidak memberi jawaban.');
        }

        return $teks;
    }

    private function anthropic(string $model, string $pesan, string $sistem, int $maks): string
    {
        $r = $this->http()->withHeaders([
            'x-api-key' => Pengaturan::ambilRahasia('ai.anthropic.kunci'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $maks,
            'system' => $sistem,
            'messages' => [['role' => 'user', 'content' => $pesan]],
        ]);
        $this->periksa($r);

        return (string) $r->json('content.0.text', '');
    }

    /** Format chat completions yang dipakai OpenAI dan OpenRouter. */
    private function openai(string $penyedia, string $url, string $model, string $pesan, string $sistem, int $maks): string
    {
        $r = $this->http()->withToken(Pengaturan::ambilRahasia("ai.$penyedia.kunci"))
            ->post($url, [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => $maks,
                'messages' => [['role' => 'system', 'content' => $sistem], ['role' => 'user', 'content' => $pesan]],
            ]);
        $this->periksa($r);

        return (string) $r->json('choices.0.message.content', '');
    }

    private function ollama(string $model, string $pesan, string $sistem): string
    {
        $url = rtrim((string) Pengaturan::ambil('ai.ollama.url'), '/');
        $r = $this->http()->timeout(180)->post($url.'/api/chat', [
            'model' => $model,
            'stream' => false,
            'options' => ['temperature' => 0.4],
            'messages' => [['role' => 'system', 'content' => $sistem], ['role' => 'user', 'content' => $pesan]],
        ]);
        $this->periksa($r);

        return (string) $r->json('message.content', '');
    }

    /** Menerjemahkan kegagalan HTTP menjadi pesan yang jelas tanpa membocorkan isi respons atau kunci. */
    private function periksa(Response $r): void
    {
        if ($r->successful()) {
            return;
        }

        throw new AiException(match (true) {
            in_array($r->status(), [401, 403], true) => 'Kunci API ditolak oleh penyedia. Periksa kembali kunci di Pengaturan AI.',
            $r->status() === 404 => 'Model tidak ditemukan. Periksa nama model di Pengaturan AI.',
            $r->status() === 429 => 'Batas penggunaan atau kuota penyedia AI terlampaui. Coba lagi beberapa saat atau periksa kuota akun.',
            $r->status() === 400 => 'Permintaan ditolak penyedia (periksa nama model dan kuota).',
            $r->serverError() => 'Layanan AI sedang bermasalah. Coba lagi nanti.',
            default => 'Layanan AI mengembalikan kesalahan ('.$r->status().').',
        });
    }
}
