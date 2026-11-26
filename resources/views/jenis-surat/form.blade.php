@php
    $edit = $jenis->exists;
    $skemaAwal = old('form_schema') ? (json_decode(old('form_schema'), true) ?: []) : ($jenis->form_schema ?: []);
    $templateAwal = old('template_html', $jenis->template_html ?? '');
    $tipe = [
        'text' => 'Teks pendek',
        'textarea' => 'Paragraf',
        'richtext' => 'Teks berformat',
        'list' => 'Daftar bernomor',
        'tabel' => 'Tabel (untuk lampiran)',
        'date' => 'Tanggal',
        'number' => 'Angka',
        'select' => 'Pilihan',
    ];
@endphp

<x-app-layout>
    <x-slot name="title">{{ $edit ? 'Ubah Jenis Surat' : 'Tambah Jenis Surat' }}</x-slot>
    <x-slot name="header"><h1 class="text-xl font-semibold text-slate-900">{{ $edit ? 'Ubah Jenis Surat' : 'Tambah Jenis Surat' }}</h1></x-slot>

    <form method="post" action="{{ $edit ? route('jenis-surat.update', $jenis) : route('jenis-surat.store') }}"
          x-data="templateBuilder(@js($skemaAwal), @js($templateAwal))" class="max-w-4xl space-y-5">
        @csrf
        @if ($edit) @method('put') @endif

        <input type="hidden" name="form_schema" data-sandi :value="JSON.stringify(skema)">
        <input type="hidden" name="template_html" data-sandi :value="templateHtml">

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
            <div>
                <x-input-label for="nama_surat" value="Nama jenis surat" />
                <x-text-input id="nama_surat" name="nama_surat" class="mt-1 block w-full" :value="old('nama_surat', $jenis->nama_surat)" required placeholder="Surat Pengantar Kerja Praktik" />
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $jenis->is_active)) class="rounded border-slate-300 text-indigo-800">
                Aktif (dapat dipilih saat membuat surat)
            </label>
            <div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="pdf_publik" value="1" @checked(old('pdf_publik', $jenis->pdf_publik)) class="rounded border-slate-300 text-indigo-800">
                    Tampilkan PDF di halaman validasi publik
                </label>
                <p class="mt-1 text-xs text-slate-600">Aktifkan hanya bila isinya aman dibaca umum, tanpa data pribadi seperti NIM, nilai, daftar peserta, atau honorarium. Berlaku untuk surat yang disetujui setelah ini.</p>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-lg p-5">
            <h2 class="font-semibold">1. Isian formulir</h2>
            <p class="mt-1 text-sm text-slate-600">Data yang diisi staff saat membuat surat jenis ini. Tujuan surat dan perihal sudah tersedia otomatis.</p>

            <div class="mt-4 space-y-3">
                <template x-for="(f, i) in fields" :key="i">
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="grid gap-3 sm:grid-cols-12">
                            <label class="sm:col-span-5 text-xs text-slate-600">Label isian
                                <input x-model="f.label" @change="beresLabel(f)" placeholder="Contoh: Nama Mahasiswa" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            </label>
                            <label class="sm:col-span-3 text-xs text-slate-600">Jenis isian
                                <select x-model="f.type" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                    @foreach ($tipe as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select>
                            </label>
                            <label class="sm:col-span-4 text-xs text-slate-600">Nilai awal (opsional)
                                <input x-model="f.default" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            </label>
                            <label x-show="f.type === 'tabel'" x-cloak class="sm:col-span-12 text-xs text-slate-600">Judul kolom awal, pisahkan dengan koma (nomor urut dibuat otomatis; staff dapat mengubahnya saat membuat surat)
                                <input x-model="f.kolom" placeholder="Nama, Modul Praktikum" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            </label>
                            <label x-show="f.type === 'select'" x-cloak class="sm:col-span-12 text-xs text-slate-600">Pilihan, pisahkan dengan koma
                                <input x-model="f.options" placeholder="Ganjil, Genap" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            </label>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <label class="inline-flex items-center gap-1.5"><input type="checkbox" x-model="f.required" class="rounded border-slate-300 text-indigo-800"> Wajib diisi</label>
                            <span class="text-xs text-slate-600">Penanda di template: <code x-text="f.name ? '@{{' + f.name + '}}' : '(muncul setelah label diisi)'"></code></span>
                            <span class="ms-auto flex items-center gap-3 text-xs">
                                <button type="button" @click="geser(i, -1)" :disabled="i === 0" class="text-slate-600 underline disabled:opacity-40">Naik</button>
                                <button type="button" @click="geser(i, 1)" :disabled="i === fields.length - 1" class="text-slate-600 underline disabled:opacity-40">Turun</button>
                                <button type="button" @click="hapus(i)" class="text-red-700 underline">Hapus</button>
                            </span>
                        </div>
                    </div>
                </template>
                <p x-show="! fields.length" class="text-sm text-slate-600">Belum ada isian khusus. Tambahkan jika surat ini butuh data seperti nama mahasiswa atau tanggal kegiatan.</p>
            </div>
            <button type="button" @click="tambah()" class="mt-3 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">+ Tambah isian</button>
        </section>

        <section class="bg-white border border-slate-200 rounded-lg p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">2. Isi template surat</h2>
                <div class="flex gap-1 text-sm" role="tablist">
                    <button type="button" role="tab" :aria-selected="(mode === 'visual').toString()" @click="mode = 'visual'" class="rounded-md px-3 py-1" :class="mode === 'visual' ? 'nm-inset font-semibold text-indigo-900' : 'text-slate-600'">Visual</button>
                    <button type="button" role="tab" :aria-selected="(mode === 'html').toString()" @click="mode = 'html'" class="rounded-md px-3 py-1" :class="mode === 'html' ? 'nm-inset font-semibold text-indigo-900' : 'text-slate-600'">HTML</button>
                </div>
            </div>
            <p class="mt-1 text-sm text-slate-600">Ketik isi surat, lalu klik penanda isian di bawah untuk menyisipkannya di posisi kursor. Kop surat dan blok tanda tangan ditambahkan otomatis.</p>

            <div x-show="mode === 'visual'" class="mt-3 mb-3 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate-600">Sisipkan:</span>
                <template x-for="n in bawaan" :key="n">
                    <button type="button" @mousedown.prevent @click="sisipField(n)" class="rounded border border-slate-300 bg-white px-2 py-0.5 font-mono text-xs hover:bg-slate-100" x-text="'@{{' + n + '}}'"></button>
                </template>
                <button type="button" @mousedown.prevent @click="sisipField('halaman_baru')" title="Teks sesudah penanda ini menjadi halaman lampiran setelah tanda tangan" class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 font-mono text-xs text-amber-900 hover:bg-amber-100">@{{halaman_baru}}</button>
                <template x-for="f in fields.filter((x) => x.name && x.label.trim())" :key="f.name">
                    <span class="inline-flex gap-1">
                        <button type="button" @mousedown.prevent @click="sisipField(f.name)" class="rounded border border-indigo-300 bg-indigo-50 px-2 py-0.5 font-mono text-xs text-indigo-900 hover:bg-indigo-100" x-text="'@{{' + f.name + '}}'"></button>
                        <button type="button" x-show="f.type === 'date'" @mousedown.prevent @click="sisipField(f.name + '_lengkap')" title="Tanggal dengan nama hari" class="rounded border border-indigo-300 bg-indigo-50 px-2 py-0.5 font-mono text-xs text-indigo-900 hover:bg-indigo-100" x-text="'@{{' + f.name + '_lengkap}}'"></button>
                    </span>
                </template>
            </div>

            <x-editor-visual id="editor-template" x-model="templateHtml" :template="true" tinggi="22rem" x-show="mode === 'visual'" />
            <textarea x-show="mode === 'html'" x-cloak x-model="templateHtml" rows="18" spellcheck="false" aria-label="Template HTML" class="mt-3 block w-full rounded-md border-slate-300 font-mono text-xs"></textarea>
            <p x-show="mode === 'html'" x-cloak class="mt-1 text-xs text-slate-600">Mode HTML untuk penyesuaian lanjutan. Tag dan gaya yang tidak aman dibersihkan otomatis saat disimpan.</p>
        </section>

        <div class="flex gap-3">
            <x-primary-button>Simpan</x-primary-button>
            <a href="{{ route('jenis-surat.index') }}" class="self-center text-sm text-slate-600 underline">Batal</a>
        </div>
    </form>
</x-app-layout>
