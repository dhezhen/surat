@props(['template' => false, 'tinggi' => '14rem', 'ai' => false])

<div x-data="editorVisual({ template: @js($template), url: @js(route('ai.susun')) })" x-modelable="html" {{ $attributes }}>
    <div class="flex flex-wrap items-center gap-1 rounded-t-md border border-b-0 border-slate-300 bg-slate-50 px-2 py-1.5 text-sm" role="toolbar" aria-label="Pemformatan teks">
        <button type="button" @mousedown.prevent @click="perintah('bold')" title="Tebal" aria-label="Tebal" class="h-8 w-8 rounded font-bold hover:bg-slate-200">B</button>
        <button type="button" @mousedown.prevent @click="perintah('italic')" title="Miring" aria-label="Miring" class="h-8 w-8 rounded italic hover:bg-slate-200">I</button>
        <button type="button" @mousedown.prevent @click="perintah('underline')" title="Garis bawah" aria-label="Garis bawah" class="h-8 w-8 rounded underline hover:bg-slate-200">U</button>
        <span class="mx-1 h-5 w-px bg-slate-300" aria-hidden="true"></span>
        <button type="button" @mousedown.prevent @click="perintah('insertOrderedList')" class="h-8 rounded px-2 hover:bg-slate-200">1. Nomor</button>
        <button type="button" @mousedown.prevent @click="perintah('insertUnorderedList')" class="h-8 rounded px-2 hover:bg-slate-200">• Butir</button>
        <span class="mx-1 h-5 w-px bg-slate-300" aria-hidden="true"></span>
        <button type="button" @mousedown.prevent @click="perintah('justifyLeft')" class="h-8 rounded px-2 hover:bg-slate-200">Kiri</button>
        <button type="button" @mousedown.prevent @click="perintah('justifyCenter')" class="h-8 rounded px-2 hover:bg-slate-200">Tengah</button>
        <button type="button" @mousedown.prevent @click="perintah('justifyFull')" class="h-8 rounded px-2 hover:bg-slate-200">Rata</button>
        <button type="button" @mousedown.prevent @click="jorok()" title="Menjorok baris pertama paragraf" class="h-8 rounded px-2 hover:bg-slate-200">Menjorok</button>
        @if ($template)
            <span class="mx-1 h-5 w-px bg-slate-300" aria-hidden="true"></span>
            <button type="button" @mousedown.prevent @click="sisipTabel()" class="h-8 rounded px-2 hover:bg-slate-200">Tabel isian</button>
            <button type="button" @mousedown.prevent @click="sisipKepala()" title="Nomor, Lampiran, Perihal, dan Kepada Yth." class="h-8 rounded px-2 hover:bg-slate-200">Kepala surat</button>
            <button type="button" @mousedown.prevent @click="sisipHalamanBaru()" title="Teks sesudah penanda ini menjadi halaman lampiran setelah tanda tangan" class="h-8 rounded px-2 hover:bg-slate-200">Halaman lampiran</button>
        @endif
        @if ($ai)
            <span class="mx-1 h-5 w-px bg-slate-300" aria-hidden="true"></span>
            <button type="button" @click="panelAi = ! panelAi" :aria-expanded="panelAi.toString()" class="h-8 rounded px-2 font-medium text-indigo-900 hover:bg-slate-200" :class="panelAi && 'nm-inset'">Susun dengan AI</button>
        @endif
    </div>

    @if ($ai)
        <div x-show="panelAi" x-cloak class="border border-b-0 border-slate-300 bg-white px-3 py-3 text-sm space-y-2">
            <label class="block font-medium text-slate-700" for="pokok-ai-{{ md5($attributes->get('x-model', 'ed')) }}">Pokok isi surat</label>
            <textarea id="pokok-ai-{{ md5($attributes->get('x-model', 'ed')) }}" x-model="pokok" rows="3" maxlength="2000" placeholder="Tulis poin-poin singkat, mis.: mohon dana honor dua narasumber kuliah umum tanggal 10–11 Desember 2026 secara daring"
                      class="block w-full rounded-md border-slate-300 text-sm"></textarea>
            <p class="text-xs text-slate-600">AI hanya menerima teks ini beserta jenis surat, perihal, dan tujuan. Jangan memasukkan data pribadi yang sensitif. Periksa hasilnya sebelum dipakai.</p>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="susunAi()" :disabled="aiMemuat" class="rounded-md bg-indigo-800 px-4 py-1.5 text-white hover:bg-indigo-700 disabled:opacity-60">
                    <span x-text="aiMemuat ? 'Menyusun…' : (hasilAi ? 'Susun ulang' : 'Susun kalimat')"></span>
                </button>
                <span x-show="aiMemuat" x-cloak class="text-slate-600">Mohon tunggu, biasanya beberapa detik.</span>
            </div>
            <p x-show="aiGalat" x-cloak x-text="aiGalat" class="text-red-700" role="alert"></p>

            <div x-show="hasilAi" x-cloak class="rounded-md border border-emerald-200 bg-emerald-50 p-3 space-y-2">
                <div class="text-xs font-medium text-emerald-900">Hasil susunan AI (belum dimasukkan ke surat)</div>
                <div class="editor-area rounded bg-white px-3 py-2" x-html="hasilAi"></div>
                <div class="flex flex-wrap gap-3">
                    <button type="button" @click="pakaiHasilAi(false)" class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-50">Sisipkan di posisi kursor</button>
                    <button type="button" @click="pakaiHasilAi(true)" class="rounded-md border border-slate-300 bg-white px-3 py-1 hover:bg-slate-50">Ganti seluruh isi</button>
                    <button type="button" @click="hasilAi = ''" class="text-slate-600 underline">Buang</button>
                </div>
            </div>
        </div>
    @endif

    <div x-ref="area" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Isi surat"
         @input="sinkron()" @keyup="simpanRentang()" @mouseup="simpanRentang()" @blur="simpanRentang()" @paste.prevent="tempel($event)"
         class="editor-area overflow-y-auto rounded-b-md border border-slate-300 bg-white px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-700"
         style="min-height: {{ $tinggi }}; max-height: 28rem"></div>
</div>
