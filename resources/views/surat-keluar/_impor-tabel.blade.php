{{-- Dipakai di dalam x-for form surat: memakai f (definisi isian) dan dinamis[f.name] (tabelnya).
     Kontrol di sini sengaja tanpa atribut name agar tidak ikut terkirim. --}}
<div x-data="imporTabel()" class="mt-3 space-y-3 rounded-md border border-slate-200 p-3 text-sm" role="group" :aria-label="'Isi ' + f.label + ' dari Excel'">
    <div>
        <p class="font-medium text-slate-900">Isi dari Excel</p>
        <p class="text-xs text-slate-600">Maksimal 8 kolom dan 300 baris. Angka diambil sebagai nilai asli sel, tanpa format rupiah atau persen.</p>
    </div>

    <label class="flex items-center gap-2">
        <input type="checkbox" x-model="pakaiJudul" @change="susunUlang()" class="rounded border-slate-300 text-indigo-800 focus:ring-indigo-500">
        Baris pertama adalah judul kolom
    </label>

    <div class="space-y-3">
        <label class="block text-xs font-medium text-slate-700">Unggah berkas .xlsx atau .csv
            <input type="file" accept=".xlsx,.xls,.csv,.tsv,.txt" @change="bacaBerkas($event.target)" :disabled="membaca"
                   class="mt-1 block w-full rounded-md text-sm font-normal text-slate-700 file:me-3 file:rounded-md file:border file:border-solid file:border-slate-300 file:bg-white file:px-3 file:py-1.5 file:text-sm file:text-slate-800">
        </label>
        <label class="block text-xs font-medium text-slate-700">Atau salin dari Excel, lalu tempel di sini
            <textarea rows="2" @paste.prevent="tempel(dinamis[f.name], $event.clipboardData.getData('text/plain'))" placeholder="Klik di sini, lalu tekan Ctrl+V" class="mt-1 block w-full rounded-md border-slate-300 text-sm font-normal"></textarea>
        </label>
    </div>

    <p role="status" class="text-slate-700" x-text="membaca ? 'Membaca berkas…' : ''"></p>
    <p role="alert" class="text-red-700" x-text="galat"></p>

    <template x-if="namaSheet.length">
        <div class="space-y-3 border-t border-slate-200 pt-3">
            <label x-show="namaSheet.length > 1" class="block text-xs font-medium text-slate-700">Sheet
                <select x-model.number="sheet" @change="susunUlang()" class="mt-1 block w-full rounded-md border-slate-300 text-sm font-normal">
                    <template x-for="(nama, n) in namaSheet" :key="n"><option :value="n" x-text="nama"></option></template>
                </select>
            </label>

            <p x-show="jumlahBaris === 0" class="text-slate-700">Sheet ini tidak berisi data. Pilih sheet lain atau berkas lain.</p>

            <fieldset x-show="jumlahBaris > 0" class="min-w-0">
                <legend class="text-xs font-medium text-slate-700">Kolom yang dimasukkan (<span x-text="pilihan.length"></span> dari maksimal <span x-text="maksKolom"></span>)</legend>
                <div class="mt-1 grid max-h-48 gap-1 overflow-y-auto sm:grid-cols-2">
                    <template x-for="k in kolomBerkas" :key="sheet + '-' + k.i + '-' + k.judul">
                        <label class="flex items-start gap-2 rounded p-1">
                            <input type="checkbox" :value="k.i" x-model.number="pilihan" :disabled="pilihan.length >= maksKolom && ! pilihan.includes(k.i)" class="mt-0.5 rounded border-slate-300 text-indigo-800 focus:ring-indigo-500 disabled:opacity-50">
                            <span class="min-w-0 break-words">
                                <span x-text="k.judul"></span>
                                <span x-show="k.contoh" class="block text-xs text-slate-600">Contoh: <span x-text="k.contoh.slice(0, 40)"></span></span>
                            </span>
                        </label>
                    </template>
                </div>
            </fieldset>

            <label x-show="jumlahBaris > 0 && dinamis[f.name].baris.some((b) => b.some((c) => String(c).trim() !== ''))" class="block text-xs font-medium text-slate-700">Tabel sudah berisi data
                <select x-model="ganti" class="mt-1 block w-full rounded-md border-slate-300 text-sm font-normal">
                    <option value="tambah">Tambahkan di bawah data yang ada</option>
                    <option value="ganti">Ganti seluruh isi tabel</option>
                </select>
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" x-show="jumlahBaris > 0" @click="terapkan(dinamis[f.name])" :disabled="! pilihan.length" class="rounded-md bg-indigo-800 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Masukkan <span x-text="jumlahBaris"></span> baris</button>
                <button type="button" @click="batal()" class="text-slate-700 underline">Batal</button>
                <span x-show="jumlahBaris > 0 && ! pilihan.length" class="text-xs text-slate-700">Pilih minimal satu kolom.</span>
            </div>
        </div>
    </template>

    <div role="status" aria-live="polite" class="space-y-2">
        <p x-show="hasil" class="text-slate-900" x-text="hasil"></p>
        <div x-show="peringatan.length" class="rounded-md border border-amber-300 bg-amber-50 p-2 text-amber-900">
            <p class="font-medium">Perlu diperiksa</p>
            <ul class="list-disc ps-5"><template x-for="p in peringatan" :key="p"><li x-text="p"></li></template></ul>
        </div>
    </div>
    <button type="button" x-show="cadangan" @click="kembalikan(dinamis[f.name])" class="text-slate-700 underline">Kembalikan tabel seperti sebelum impor</button>
</div>
