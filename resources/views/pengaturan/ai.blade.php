@php
    $catatan = [
        'gemini' => ['asal' => 'Buat kunci di Google AI Studio (aistudio.google.com/apikey).', 'privasi' => 'Pada tingkat gratis, Google dapat memakai isi permintaan untuk meningkatkan produknya. Gunakan akun berbayar bila isi surat memuat data pribadi.'],
        'anthropic' => ['asal' => 'Buat kunci di console.anthropic.com.', 'privasi' => 'Data yang dikirim lewat API tidak dipakai untuk melatih model secara bawaan. Penggunaan berbayar sejak permintaan pertama.'],
        'openai' => ['asal' => 'Buat kunci di platform.openai.com/api-keys.', 'privasi' => 'Data yang dikirim lewat API tidak dipakai untuk melatih model secara bawaan. Penggunaan berbayar.'],
        'openrouter' => ['asal' => 'Buat kunci di openrouter.ai/keys. Model berlabel ":free" tidak ditagih tetapi dibatasi jumlah permintaan per hari.', 'privasi' => 'Permintaan diteruskan ke penyedia model yang dipilih. Pada model gratis, penyedia tersebut dapat memakai isi permintaan untuk pelatihan, jadi jangan kirim data pribadi sensitif.'],
        'ollama' => ['asal' => 'Pasang Ollama di komputer atau server sendiri dan unduh sebuah model (mis. llama3.1).', 'privasi' => 'Isi surat tidak keluar dari jaringan Anda. Mutu bahasa mengikuti model yang dipakai dan kebutuhan perangkat keras lebih tinggi.'],
    ];
@endphp

<x-app-layout>
    <x-slot name="title">Pengaturan AI</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Pengaturan AI</h1>
            <p class="text-sm text-slate-600">Penyusun kalimat surat untuk Staff Prodi. Hasilnya selalu diperiksa staff sebelum disimpan.</p>
        </div>
    </x-slot>

    @error('uji')<div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Uji koneksi gagal: {{ $message }}</div>@enderror

    <form method="post" action="{{ route('pengaturan.ai.update') }}" autocomplete="off" x-data="{ penyedia: @js(old('penyedia', $terpilih)) }" class="max-w-3xl space-y-5">
        @csrf
        @method('put')

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
            <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $aktif)) class="rounded border-slate-300 text-indigo-800">
                Aktifkan fitur "Susun dengan AI" di editor isi surat
            </label>

            <div>
                <x-input-label for="penyedia" value="Penyedia AI" />
                <select id="penyedia" name="penyedia" x-model="penyedia" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    @foreach ($penyedia as $id => $p)
                        <option value="{{ $id }}">{{ $p['label'] }}{{ $p['siap'] ? ' — siap' : ' — belum dikonfigurasi' }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        @foreach ($penyedia as $id => $p)
            <section x-show="penyedia === '{{ $id }}'" @if ($id !== $terpilih && old('penyedia') !== $id) x-cloak @endif class="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
                <h2 class="font-semibold">{{ $p['label'] }}</h2>
                <p class="text-sm text-slate-600">{{ $catatan[$id]['asal'] }}</p>

                @if ($p['kunci'])
                    <div>
                        <x-input-label :for="'kunci_'.$id" value="Kunci API" />
                        @if ($p['kunciTersimpan'])
                            <p class="mt-1 text-sm text-emerald-800">Tersimpan terenkripsi (berakhiran <span class="font-mono">…{{ $p['kunciAkhir'] }}</span>). Isi kolom di bawah hanya jika ingin menggantinya.</p>
                        @endif
                        <input id="kunci_{{ $id }}" name="kunci[{{ $id }}]" type="password" autocomplete="new-password" spellcheck="false"
                               placeholder="{{ $p['kunciTersimpan'] ? 'Kunci baru (opsional)' : 'Tempel kunci API di sini' }}"
                               class="mt-1 block w-full rounded-md border-slate-300 font-mono text-sm">
                        @if ($p['kunciTersimpan'])
                            <label class="mt-2 inline-flex items-center gap-2 text-sm text-red-800">
                                <input type="checkbox" name="hapus_kunci[{{ $id }}]" value="1" class="rounded border-slate-300"> Hapus kunci yang tersimpan
                            </label>
                        @endif
                    </div>
                @else
                    <div>
                        <x-input-label for="url_ollama" value="Alamat server Ollama" />
                        <x-text-input id="url_ollama" name="url_ollama" class="mt-1 block w-full font-mono" :value="old('url_ollama', $p['url'])" placeholder="http://localhost:11434" />
                    </div>
                @endif

                @php
                    $awal = old('model.'.$id, $p['modelTersimpan']) ?: $p['model'];
                    $dikenal = array_key_exists($awal, $p['pilihan']);
                @endphp
                <div x-data="{ pilih: @js($dikenal ? $awal : '__lain'), lain: @js($dikenal ? '' : $awal) }">
                    <x-input-label :for="'model_'.$id" value="Model" />
                    <select id="model_{{ $id }}" x-model="pilih" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @foreach ($p['pilihan'] as $nama => $keterangan)
                            <option value="{{ $nama }}">{{ $keterangan }}{{ $nama === $p['model'] ? ' (bawaan)' : '' }}</option>
                        @endforeach
                        <option value="__lain">Lainnya: ketik nama model sendiri</option>
                    </select>
                    <input type="hidden" name="model[{{ $id }}]" :value="pilih === '__lain' ? lain : pilih">
                    <div x-show="pilih === '__lain'" x-cloak class="mt-2">
                        <label for="model_lain_{{ $id }}" class="sr-only">Nama model</label>
                        <input id="model_lain_{{ $id }}" type="text" x-model="lain" maxlength="100" spellcheck="false" placeholder="{{ $p['model'] }}" class="block w-full rounded-md border-slate-300 font-mono text-sm">
                    </div>
                    <p class="mt-1 text-xs text-slate-600">Daftar model berubah-ubah. Jika uji koneksi menyatakan model tidak ditemukan, pilih "Lainnya" dan ketik nama model terbaru dari penyedia.</p>
                </div>

                <p class="rounded-md bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-700"><strong>Privasi:</strong> {{ $catatan[$id]['privasi'] }}</p>
            </section>
        @endforeach

        <section class="bg-white border border-slate-200 rounded-lg p-5 text-sm text-slate-700 space-y-1">
            <h2 class="font-semibold text-slate-900">Yang dikirim ke penyedia AI</h2>
            <p>Hanya pokok isi yang diketik staff, ditambah jenis surat, perihal, dan tujuan surat pada form. Data akun, nomor surat, dan surat lain tidak dikirim. Kunci API disimpan terenkripsi di database dan tidak pernah dikirim ke browser.</p>
        </section>

        <div class="flex gap-3">
            <x-primary-button>Simpan pengaturan</x-primary-button>
        </div>
    </form>

    <form method="post" action="{{ route('pengaturan.ai.uji') }}" class="mt-4 max-w-3xl">
        @csrf
        <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Uji koneksi</button>
        <span class="ms-2 text-sm text-slate-600">Menguji penyedia yang sudah tersimpan. Simpan dulu bila baru mengubah pengaturan.</span>
    </form>
</x-app-layout>
