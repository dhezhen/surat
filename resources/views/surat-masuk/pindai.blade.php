@php
    $kelasInput = 'mt-1 block w-full rounded-md border-slate-300 text-sm disabled:bg-slate-50 disabled:text-slate-600';
    $kolom = [
        'nomor_surat_asal' => ['label' => 'Nomor surat asal', 'tipe' => 'text', 'lebar' => ''],
        'pengirim' => ['label' => 'Pengirim / asal instansi', 'tipe' => 'text', 'lebar' => ''],
        'tanggal_surat' => ['label' => 'Tanggal surat', 'tipe' => 'date', 'lebar' => ''],
        'tanggal_diterima' => ['label' => 'Tanggal diterima', 'tipe' => 'date', 'lebar' => ''],
        'perihal' => ['label' => 'Perihal / ringkasan isi', 'tipe' => 'text', 'lebar' => 'sm:col-span-2'],
    ];
@endphp

<x-app-layout>
    <x-slot name="title">Pindai Banyak Surat</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Pindai Banyak Surat Masuk</h1>
            <p class="text-sm text-slate-600">Foto surat satu per satu. Tiap surat dibaca otomatis, Anda periksa, lalu disimpan sebagai arsip sendiri dengan nomor agenda berurutan.</p>
        </div>
    </x-slot>

    <div x-data="pindaiBanyak(@js([
            'kategori' => $kategori,
            'basis' => asset('vendor/ocr'),
            'tanggal' => now()->format('Y-m-d'),
            'urlSimpan' => route('surat-masuk.store'),
        ]))"
         @pindai-selesai="terima($event.detail)"
         class="max-w-5xl space-y-5 pb-24">

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-awal">
            <h2 id="judul-awal" class="font-semibold">Isian awal untuk surat yang dipindai berikutnya</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="awal_tanggal" class="block text-sm font-medium text-slate-700">Tanggal diterima</label>
                    <input id="awal_tanggal" type="date" x-model="bawaan.tanggal_diterima" class="{{ $kelasInput }}">
                </div>
                <div>
                    <label for="awal_kategori" class="block text-sm font-medium text-slate-700">Kategori</label>
                    <select id="awal_kategori" x-model="bawaan.kategori" class="{{ $kelasInput }}">
                        @foreach ($kategori as $k)
                            <option value="{{ $k }}">{{ $k }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-600">Diganti bila OCR mengenali kategori surat.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <x-kamera-pindai batch label="Mulai memindai" />
                <span class="text-sm text-slate-600">Setelah semua halaman satu surat difoto, tekan <strong>Surat berikutnya</strong>.</span>
            </div>
        </section>

        <p x-show="! surat.length" class="rounded-lg border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-600">
            Belum ada surat. Tekan <strong>Mulai memindai</strong>. Surat yang sudah difoto muncul di sini untuk diperiksa.
        </p>

        <ol class="space-y-4" aria-label="Surat yang dipindai">
            <template x-for="(s, i) in surat" :key="s.id">
                <li class="bg-white border rounded-lg p-4 sm:p-5"
                    :class="s.simpan === 'tersimpan' ? 'border-emerald-300' : (s.simpan === 'gagal' || s.baca === 'gagal' ? 'border-red-300' : 'border-slate-200')">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h2 class="font-semibold">
                            Surat <span x-text="i + 1"></span>
                            <span class="font-normal text-slate-600" x-text="`(${s.halaman.length} halaman, ${s.berkas.type === 'application/pdf' ? 'PDF' : 'JPG'})`"></span>
                        </h2>
                        <button type="button" @click="hapus(s)" x-show="s.simpan !== 'menyimpan'"
                                class="inline-flex min-h-[44px] items-center rounded-md px-2 text-sm text-red-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700"
                                x-text="s.simpan === 'tersimpan' ? 'Sembunyikan' : 'Hapus dari daftar'"></button>
                    </div>

                    <div class="mt-3 grid gap-4 md:grid-cols-[9rem_minmax(0,1fr)]">
                        <div class="flex gap-2 overflow-x-auto md:flex-col md:overflow-visible">
                            <template x-for="(h, j) in s.halaman" :key="h.url">
                                <a :href="h.url" target="_blank" rel="noopener" class="block shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                                    <img :src="h.url" :alt="`Surat ${i + 1}, halaman ${j + 1}. Buka ukuran penuh`" class="h-32 w-24 rounded border border-slate-200 bg-white object-cover md:h-auto md:w-36">
                                </a>
                            </template>
                        </div>

                        <div class="space-y-3">
                            <div aria-live="polite" class="text-sm">
                                <div x-show="s.baca === 'antre'" class="text-slate-600">Menunggu giliran dibaca.</div>
                                <div x-show="s.baca === 'membaca'">
                                    <div class="flex justify-between text-slate-700"><span x-text="s.pesan"></span><span x-text="s.persen + '%'"></span></div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="s.persen" :aria-label="`Membaca surat ${i + 1}`">
                                        <div class="h-full bg-indigo-800 transition-all" :style="`width: ${s.persen}%`"></div>
                                    </div>
                                </div>
                                <p x-show="s.baca === 'selesai' && s.simpan !== 'tersimpan'" class="text-slate-700">
                                    <strong x-text="s.jumlahTerbaca"></strong> dari 5 kolom terbaca. Kolom bertanda kuning perlu dicocokkan dengan foto.
                                </p>
                                <div x-show="s.baca === 'gagal'" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-800">
                                    Teks tidak terbaca: <span x-text="s.pesan"></span>. Isi kolom secara manual, atau
                                    <button type="button" @click="bacaUlang(s)" class="underline">baca ulang</button>.
                                </div>
                                <div x-show="s.simpan === 'tersimpan'" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-900">
                                    Tersimpan dengan nomor agenda <strong class="font-mono" x-text="s.arsip?.nomor_agenda"></strong>.
                                    <a :href="s.arsip?.url" class="underline">Buka arsip</a>
                                </div>
                                <p x-show="s.galatUmum" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-800" x-text="s.galatUmum"></p>
                            </div>

                            <fieldset class="grid gap-3 sm:grid-cols-2" :disabled="s.simpan === 'tersimpan' || s.simpan === 'menyimpan' || s.baca === 'membaca' || s.baca === 'antre'">
                                <legend class="sr-only" x-text="`Data surat ${i + 1}`"></legend>
                                @foreach ($kolom as $nama => $k)
                                    <div class="{{ $k['lebar'] }}">
                                        <label :for="`s${s.id}_{{ $nama }}`" class="block text-sm font-medium text-slate-700">{{ $k['label'] }}</label>
                                        <input :id="`s${s.id}_{{ $nama }}`" type="{{ $k['tipe'] }}" x-model="s.f.{{ $nama }}" @input="sentuh(s, '{{ $nama }}')" required
                                               class="{{ $kelasInput }}"
                                               :class="s.galat.{{ $nama }} ? 'border-red-500 ring-1 ring-red-400' : (s.dariOcr.{{ $nama }} && 'border-amber-500 ring-1 ring-amber-400')"
                                               :aria-invalid="!! s.galat.{{ $nama }}">
                                        <p x-show="s.galat.{{ $nama }}" class="mt-1 text-xs text-red-700" x-text="s.galat.{{ $nama }}"></p>
                                        <p x-show="! s.galat.{{ $nama }} && s.dariOcr.{{ $nama }}" class="mt-1 text-xs text-amber-800">Terisi dari OCR, mohon periksa.</p>
                                    </div>
                                @endforeach
                                <div>
                                    <label :for="`s${s.id}_kategori`" class="block text-sm font-medium text-slate-700">Kategori</label>
                                    <select :id="`s${s.id}_kategori`" x-model="s.f.kategori" @change="sentuh(s, 'kategori')" class="{{ $kelasInput }}"
                                            :class="s.galat.kategori ? 'border-red-500 ring-1 ring-red-400' : (s.dariOcr.kategori && 'border-amber-500 ring-1 ring-amber-400')">
                                        @foreach ($kategori as $k)
                                            <option value="{{ $k }}">{{ $k }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="s.galat.kategori" class="mt-1 text-xs text-red-700" x-text="s.galat.kategori"></p>
                                </div>
                                <div>
                                    <label :for="`s${s.id}_keterangan`" class="block text-sm font-medium text-slate-700">Keterangan (opsional)</label>
                                    <input :id="`s${s.id}_keterangan`" x-model="s.f.keterangan" class="{{ $kelasInput }}">
                                </div>
                                <p x-show="s.galat.file_scan" class="sm:col-span-2 text-sm text-red-700" x-text="s.galat.file_scan"></p>
                            </fieldset>

                            <div x-show="s.simpan !== 'tersimpan'">
                                <button type="button" @click="simpan(s)" :disabled="! bisaDisimpan(s)"
                                        class="inline-flex min-h-[44px] items-center rounded-md bg-indigo-800 px-4 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2 disabled:opacity-50"
                                        x-text="s.simpan === 'menyimpan' ? 'Menyimpan…' : 'Simpan surat ini'"></button>
                            </div>
                        </div>
                    </div>
                </li>
            </template>
        </ol>

        <div x-show="surat.length" x-cloak class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 lg:left-72">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-700" aria-live="polite">
                    <span x-text="surat.length"></span> surat, <span x-text="jumlahTersimpan"></span> tersimpan<span x-show="ocrJalan">, sedang membaca</span>.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('surat-masuk.index') }}" x-show="! belumTersimpan.length" class="inline-flex min-h-[44px] items-center rounded-md border border-slate-300 bg-white px-4 text-sm hover:bg-slate-50">Ke arsip surat masuk</a>
                    <button type="button" @click="simpanSemua()" :disabled="! siapDisimpan.length || menyimpanSemua"
                            class="inline-flex min-h-[44px] items-center rounded-md bg-indigo-800 px-4 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2 disabled:opacity-50"
                            x-text="menyimpanSemua ? 'Menyimpan…' : `Simpan ${siapDisimpan.length} surat yang siap`"></button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
