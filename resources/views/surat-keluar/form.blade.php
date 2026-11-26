@php
    $edit = $surat->exists;
    $awal = [
        'jenis' => (int) old('jenis_surat_id', $surat->jenis_surat_id ?: ($jenisList->first()['id'] ?? 0)),
        'nomor' => $nomorPerkiraan,
        'tanggal' => old('tanggal_surat', optional($surat->tanggal_surat)->format('Y-m-d') ?? now()->format('Y-m-d')),
        'tujuan' => old('tujuan_surat', $surat->tujuan_surat ?? ''),
        'perihal' => old('perihal', $surat->perihal ?? ''),
        'metodeTtd' => old('metode_ttd', $surat->metodeTtd($penandatangan)),
        'dinamis' => old('dinamis', $surat->data_dinamis ?: new stdClass),
    ];
@endphp

<x-app-layout>
    <x-slot name="title">{{ $edit ? 'Ubah Surat' : 'Buat Surat Keluar' }}</x-slot>
    <x-slot name="lebar">max-w-[100rem]</x-slot>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-slate-900">{{ $edit ? 'Ubah Surat Keluar' : 'Buat Surat Keluar' }}</h1>
    </x-slot>

    @if ($jenisList->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Belum ada jenis surat aktif. <a class="underline" href="{{ route('jenis-surat.create') }}">Tambahkan jenis surat</a> terlebih dahulu.
        </div>
    @else
    <form method="post" enctype="multipart/form-data"
          action="{{ $edit ? route('surat-keluar.update', $surat) : route('surat-keluar.store') }}"
          x-data="suratForm(@js($jenisList), @js($awal))" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        @csrf
        @if ($edit) @method('put') @endif

        <div class="min-w-0 space-y-5">
            @if ($surat->status === 'revisi' && $surat->catatan_revisi)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm">
                    <div class="font-semibold text-red-800">Catatan revisi dari Ketua Prodi</div>
                    <p class="mt-1 whitespace-pre-line text-red-900">{{ $surat->catatan_revisi }}</p>
                </div>
            @endif

            <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
                <div class="rounded-md bg-slate-50 border border-slate-200 px-4 py-3">
                    <div class="text-xs text-slate-600">Nomor surat (perkiraan)</div>
                    <div class="font-mono text-base font-semibold text-indigo-900" x-text="nomor">{{ $nomorPerkiraan }}</div>
                    <p class="mt-1 text-xs text-slate-600">Nomor resmi ditetapkan saat Ketua Prodi menyetujui. Angkanya bisa bergeser bila surat lain terbit lebih dulu.</p>
                </div>
                <div>
                    <x-input-label for="jenis_surat_id" value="Jenis surat" />
                    <select id="jenis_surat_id" name="jenis_surat_id" x-model.number="jenisId" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        <template x-for="j in jenisList" :key="j.id">
                            <option :value="j.id" :selected="j.id === jenisId" x-text="j.nama"></option>
                        </template>
                    </select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="tanggal_surat" value="Tanggal surat" />
                        <input id="tanggal_surat" name="tanggal_surat" type="date" x-model="tanggal" required class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Diperbarui otomatis ke tanggal persetujuan saat surat terbit.</p>
                    </div>
                    <div>
                        <x-input-label for="tujuan_surat" value="Tujuan surat (instansi/orang)" />
                        <textarea id="tujuan_surat" name="tujuan_surat" x-model="tujuan" required maxlength="255" rows="3" placeholder="Nama jabatan atau instansi (baris pertama dicetak tebal)&#10;Baris berikutnya, mis. nama universitas" class="mt-1 block w-full rounded-md border-slate-300 text-sm"></textarea>
                        <p class="mt-1 text-xs text-slate-500">Satu baris per bagian; baris pertama dicetak tebal.</p>
                    </div>
                </div>
                <div>
                    <x-input-label for="perihal" value="Perihal" />
                    <input id="perihal" name="perihal" x-model="perihal" required maxlength="255" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <x-pilihan-ttd :terpilih="$awal['metodeTtd']" x-model="metodeTtd" />
                    <p class="mt-1 text-xs text-slate-500">Ketua Prodi dapat mengganti pilihan ini saat menyetujui.</p>
                    @unless ($penandatangan?->punyaTandaTangan())
                        <p x-show="metodeTtd === 'digital'" x-cloak class="mt-1 text-xs text-amber-800">Ketua Prodi belum mengunggah gambar tanda tangan. Surat dengan TTD digital baru bisa disetujui setelah gambar diunggah di menu Profil.</p>
                    @endunless
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" x-show="schema.length" x-cloak>
                <h2 class="font-semibold">Data khusus <span x-text="jenis ? jenis.nama : ''"></span></h2>
                <template x-for="f in schema" :key="jenisId + f.name">
                    <div>
                        <label class="block text-sm font-medium text-slate-700" :for="'f_' + f.name">
                            <span x-text="f.label"></span><span x-show="f.required" class="text-red-600"> *</span>
                        </label>
                        <template x-if="['textarea', 'list'].includes(f.type)">
                            <textarea :id="'f_' + f.name" :name="'dinamis[' + f.name + ']'" x-model="dinamis[f.name]" :rows="f.type === 'list' ? 4 : 3" :required="f.required" maxlength="3000" class="mt-1 block w-full rounded-md border-slate-300 text-sm"></textarea>
                        </template>
                        <template x-if="f.type === 'richtext'">
                            <div class="mt-1">
                                <x-editor-visual x-model="dinamis[f.name]" tinggi="16rem" :ai="$aiTersedia"
                                                 x-bind:data-konteks="JSON.stringify({ perihal: perihal, tujuan: tujuan, jenis: jenis ? jenis.nama : '' })" />
                                <input type="hidden" :name="'dinamis[' + f.name + ']'" data-sandi :value="dinamis[f.name]">
                            </div>
                        </template>
                        <template x-if="f.type === 'tabel' && dinamis[f.name] && dinamis[f.name].kolom">
                            <div class="mt-1">
                                <div class="max-h-96 overflow-auto rounded-md border border-slate-300">
                                    <table class="min-w-full text-sm">
                                        <thead class="sticky top-0 bg-slate-100">
                                            <tr>
                                                <th class="w-10 px-2 py-1 text-xs font-medium text-slate-500">No</th>
                                                <template x-for="(k, j) in dinamis[f.name].kolom" :key="'k' + j">
                                                    <th class="p-1"><input x-model="dinamis[f.name].kolom[j]" maxlength="60" placeholder="Judul kolom" :aria-label="'Judul kolom ' + (j + 1)" class="w-full min-w-[8rem] rounded border-slate-300 text-sm font-semibold"></th>
                                                </template>
                                                <th class="w-8"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(b, i) in dinamis[f.name].baris" :key="'b' + i">
                                                <tr class="border-t border-slate-200">
                                                    <td class="px-2 text-center text-xs text-slate-500" x-text="i + 1"></td>
                                                    <template x-for="(c, j) in dinamis[f.name].kolom" :key="'c' + i + '-' + j">
                                                        <td class="p-1"><input x-model="dinamis[f.name].baris[i][j]" maxlength="255" :aria-label="'Baris ' + (i + 1) + ', ' + dinamis[f.name].kolom[j]" class="w-full min-w-[8rem] rounded border-slate-300 text-sm"></td>
                                                    </template>
                                                    <td class="px-1"><button type="button" @click="hapusBaris(f, i)" class="text-red-700 hover:underline" :aria-label="'Hapus baris ' + (i + 1)" title="Hapus baris">&times;</button></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                    <button type="button" @click="tambahBaris(f)" :disabled="dinamis[f.name].baris.length >= 300" class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-50 disabled:opacity-40">+ Baris</button>
                                    <button type="button" @click="tambahKolom(f)" :disabled="dinamis[f.name].kolom.length >= 8" class="text-slate-700 underline disabled:opacity-40">+ Kolom</button>
                                    <button type="button" @click="hapusKolom(f)" :disabled="dinamis[f.name].kolom.length <= 1" class="text-slate-700 underline disabled:opacity-40">Kurangi kolom</button>
                                </div>
                                <input type="hidden" :name="'dinamis[' + f.name + ']'" data-sandi :value="JSON.stringify(dinamis[f.name])">
                                @include('surat-keluar._impor-tabel')
                            </div>
                        </template>
                        <p x-show="f.type === 'list'" class="mt-1 text-xs text-slate-500">Satu butir per baris; nomor dibuat otomatis.</p>
                        <template x-if="f.type === 'select'">
                            <select :id="'f_' + f.name" :name="'dinamis[' + f.name + ']'" x-model="dinamis[f.name]" :required="f.required" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                <option value="">— Pilih —</option>
                                <template x-for="o in (f.options || [])" :key="o"><option :value="o" x-text="o"></option></template>
                            </select>
                        </template>
                        <template x-if="['text', 'date', 'number'].includes(f.type || 'text')">
                            <input :id="'f_' + f.name" :name="'dinamis[' + f.name + ']'" :type="f.type || 'text'" x-model="dinamis[f.name]" :required="f.required" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        </template>
                    </div>
                </template>
            </section>

            <div class="flex flex-wrap gap-3">
                <button name="aksi" value="simpan" class="rounded-md border border-slate-300 bg-white px-5 py-2 text-sm font-medium hover:bg-slate-50">Simpan sebagai draf</button>
                <button name="aksi" value="ajukan" class="rounded-md bg-indigo-800 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan &amp; ajukan ke Kaprodi</button>
                <a href="{{ $edit ? route('surat-keluar.show', $surat) : route('surat-keluar.index') }}" class="self-center text-sm text-slate-600 underline">Batal</a>
            </div>
        </div>

        <div class="xl:sticky xl:top-4 xl:self-start xl:max-h-[calc(100vh-2rem)] xl:overflow-y-auto">
            <div class="text-sm font-semibold mb-2">Pratinjau surat (A4)</div>
            <x-kertas-pas>
                @include('surat-keluar._kertas', ['isi' => '', 'ttd' => null, 'qr' => null, 'live' => true])
            </x-kertas-pas>
        </div>
    </form>
    @endif

    @push('scripts')
    <script>
        function suratForm(jenisList, awal) {
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c]));
            const tgl = (v) => {
                if (!v) return '';
                const d = new Date(v + 'T00:00:00');
                return isNaN(d) ? '' : d.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
            };
            return {
                jenisList,
                nomor: awal.nomor,
                jenisId: awal.jenis,
                tanggal: awal.tanggal,
                tujuan: awal.tujuan,
                perihal: awal.perihal,
                metodeTtd: awal.metodeTtd,
                dinamis: Array.isArray(awal.dinamis) ? {} : awal.dinamis,
                init() {
                    this.isiDefault();
                    this.$watch('jenisId', () => this.isiDefault());
                },
                isiDefault() {
                    this.schema.forEach((f) => {
                        if (f.type === 'tabel') {
                            const t = this.dinamis[f.name];
                            if (!t || typeof t !== 'object' || !Array.isArray(t.kolom) || !t.kolom.length) {
                                const kolom = [...(f.kolom && f.kolom.length ? f.kolom : ['Nama', 'Keterangan'])];
                                this.dinamis[f.name] = {kolom, baris: [kolom.map(() => '')]};
                            }
                            return;
                        }
                        if ((this.dinamis[f.name] ?? '') === '' && f.default) this.dinamis[f.name] = f.default;
                    });
                },
                tambahBaris(f) {
                    const t = this.dinamis[f.name];
                    t.baris.push(t.kolom.map(() => ''));
                },
                hapusBaris(f, i) {
                    const t = this.dinamis[f.name];
                    t.baris.splice(i, 1);
                    if (!t.baris.length) t.baris.push(t.kolom.map(() => ''));
                },
                tambahKolom(f) {
                    const t = this.dinamis[f.name];
                    if (t.kolom.length >= 8) return;
                    t.kolom.push('');
                    t.baris.forEach((b) => b.push(''));
                },
                hapusKolom(f) {
                    const t = this.dinamis[f.name];
                    if (t.kolom.length <= 1) return;
                    t.kolom.pop();
                    t.baris.forEach((b) => b.pop());
                },
                get jenis() { return this.jenisList.find((j) => j.id === this.jenisId); },
                get schema() { return this.jenis ? this.jenis.schema : []; },
                get tanggalPanjang() { return tgl(this.tanggal); },
                get isi() { return this.bagian.isi; },
                get lampiran() { return this.bagian.lampiran; },
                tabelHtml(t) {
                    if (!t || !Array.isArray(t.kolom) || !Array.isArray(t.baris)) return '';
                    const baris = t.baris.filter((b) => b.some((c) => String(c).trim() !== ''));
                    if (!baris.length) return '';
                    const sel = 'border:1px solid #000; padding:3px 6px; vertical-align:top;';
                    return '<table style="width:100%; border-collapse:collapse; margin-bottom:8px"><thead><tr><th style="' + sel + ' width:1.2cm; text-align:center; background:#e5e7eb">No</th>'
                        + t.kolom.map((k, j) => '<th style="' + sel + ' text-align:left; background:#e5e7eb">' + esc(String(k).trim() || 'Kolom ' + (j + 1)) + '</th>').join('')
                        + '</tr></thead><tbody>'
                        + baris.map((b, i) => '<tr><td style="' + sel + ' text-align:center">' + (i + 1) + '</td>' + t.kolom.map((_, j) => '<td style="' + sel + '">' + esc(b[j] ?? '') + '</td>').join('') + '</tr>').join('')
                        + '</tbody></table>';
                },
                get bagian() {
                    if (!this.jenis) return {isi: '', lampiran: ''};
                    const v = {
                        nomor_surat: '<span title="Nomor final ditetapkan saat disetujui Ketua Prodi">' + esc(this.nomor) + ' <em style="color:#92400e">(perkiraan)</em></span>',
                        tanggal_surat: esc(tgl(this.tanggal)),
                        tujuan_surat: (() => {
                            const b = String(this.tujuan).split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
                            return b.length ? '<strong>' + esc(b[0]) + '</strong>' + b.slice(1).map((x) => '<br>' + esc(x)).join('') : '';
                        })(),
                        perihal: esc(this.perihal),
                    };
                    this.schema.forEach((f) => {
                        const x = this.dinamis[f.name] ?? '';
                        if (f.type === 'date') {
                            v[f.name] = esc(tgl(x));
                            const d = x ? new Date(x + 'T00:00:00') : null;
                            v[f.name + '_lengkap'] = d && !isNaN(d) ? esc(d.toLocaleDateString('id-ID', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'})) : '';
                        }
                        else if (f.type === 'textarea') v[f.name] = esc(x).replace(/\n/g, '<br>');
                        else if (f.type === 'richtext') v[f.name] = String(x);
                        else if (f.type === 'tabel') v[f.name] = this.tabelHtml(x);
                        else if (f.type === 'list') {
                            const baris = String(x).split(/\r?\n/).map((b) => b.trim()).filter(Boolean);
                            v[f.name] = baris.length ? '<ol style="margin:0 0 8px 0; padding-left:1.2cm">' + baris.map((b) => '<li>' + esc(b) + '</li>').join('') + '</ol>' : '';
                        } else v[f.name] = esc(x);
                    });
                    const isiTpl = (t) => t.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (m, k) => v[k] ?? '');
                    const [depan, ...belakang] = this.jenis.template.split(/\{\{\s*halaman_baru\s*\}\}/);
                    const lampiran = isiTpl(belakang.join(''));
                    const kosong = lampiran.replace(/<[^>]*>/g, '').trim() === '' && !lampiran.includes('<table');
                    return {isi: isiTpl(depan), lampiran: kosong ? '' : lampiran};
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
