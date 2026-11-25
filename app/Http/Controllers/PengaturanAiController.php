<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Services\Ai\AiException;
use App\Services\Ai\AiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanAiController extends Controller
{
    public function edit()
    {
        $penyedia = [];
        foreach (AiService::PENYEDIA as $id => $info) {
            $kunci = $info['kunci'] ? Pengaturan::ambilRahasia("ai.$id.kunci") : null;
            $penyedia[$id] = $info + [
                'id' => $id,
                'modelTersimpan' => Pengaturan::ambil("ai.$id.model") ?? '',
                'url' => Pengaturan::ambil("ai.$id.url") ?? '',
                // Kunci tidak pernah dikirim ke browser; hanya empat karakter terakhir sebagai penanda.
                'kunciTersimpan' => $kunci !== null,
                'kunciAkhir' => $kunci !== null ? substr($kunci, -4) : null,
                'siap' => AiService::punyaKonfigurasi($id),
            ];
        }

        return view('pengaturan.ai', [
            'penyedia' => $penyedia,
            'aktif' => Pengaturan::ambil('ai.aktif') === '1',
            'terpilih' => AiService::penyediaAktif() ?? 'gemini',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'penyedia' => ['required', Rule::in(array_keys(AiService::PENYEDIA))],
            'model' => ['nullable', 'array'],
            'model.*' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/\-]*$/'],
            'kunci' => ['nullable', 'array'],
            'kunci.*' => ['nullable', 'string', 'max:300'],
            'hapus_kunci' => ['nullable', 'array'],
            'url_ollama' => ['nullable', 'url:http,https', 'max:255'],
        ], [
            'model.*.regex' => 'Nama model hanya boleh berisi huruf, angka, titik, titik dua, garis miring, dan tanda hubung.',
            'url_ollama.url' => 'Alamat Ollama harus berupa URL http/https yang valid, mis. http://localhost:11434.',
        ]);

        foreach (AiService::PENYEDIA as $id => $info) {
            $model = trim((string) ($data['model'][$id] ?? ''));
            Pengaturan::simpan("ai.$id.model", $model !== '' ? $model : null);

            if ($info['kunci']) {
                if (! empty($data['hapus_kunci'][$id])) {
                    Pengaturan::simpanRahasia("ai.$id.kunci", null);
                } elseif (! empty(trim((string) ($data['kunci'][$id] ?? '')))) {
                    Pengaturan::simpanRahasia("ai.$id.kunci", trim($data['kunci'][$id]));
                }
            }
        }
        Pengaturan::simpan('ai.ollama.url', ! empty($data['url_ollama']) ? rtrim($data['url_ollama'], '/') : null);
        Pengaturan::simpan('ai.penyedia', $data['penyedia']);

        $aktif = $request->boolean('aktif');
        if ($aktif && ! AiService::punyaKonfigurasi($data['penyedia'])) {
            Pengaturan::simpan('ai.aktif', '0');
            throw ValidationException::withMessages([
                'aktif' => 'Fitur AI belum dapat diaktifkan: '.AiService::PENYEDIA[$data['penyedia']]['label'].' belum memiliki '
                    .(AiService::PENYEDIA[$data['penyedia']]['kunci'] ? 'kunci API.' : 'alamat server.'),
            ]);
        }
        Pengaturan::simpan('ai.aktif', $aktif ? '1' : '0');

        return back()->with('status', 'Pengaturan AI disimpan.');
    }

    /** Menguji penyedia terpilih dengan pengaturan yang sudah tersimpan. */
    public function uji(AiService $ai): RedirectResponse
    {
        try {
            $balasan = $ai->uji();
        } catch (AiException $e) {
            return back()->withErrors(['uji' => $e->getMessage()]);
        }

        $p = AiService::penyediaAktif();

        return back()->with('status', 'Terhubung ke '.AiService::PENYEDIA[$p]['label'].' (model '.AiService::model($p).'). Balasan uji: '.$balasan);
    }
}
