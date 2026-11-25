@php
    $edit = $surat->exists;
    $daftarKategori = \App\Models\SuratMasuk::pilihanKategori($surat);
    $nilai = [
        'nomor_surat_asal' => old('nomor_surat_asal', $surat->nomor_surat_asal ?? ''),
        'tanggal_surat' => old('tanggal_surat', optional($surat->tanggal_surat)->format('Y-m-d') ?? ''),
        'tanggal_diterima' => old('tanggal_diterima', optional($surat->tanggal_diterima)->format('Y-m-d') ?? ''),
        'pengirim' => old('pengirim', $surat->pengirim ?? ''),
        'perihal' => old('perihal', $surat->perihal ?? ''),
        'kategori' => old('kategori', $surat->kategori ?: $daftarKategori[0]),
        'keterangan' => old('keterangan', $surat->keterangan ?? ''),
    ];
    $kelasInput = 'mt-1 block w-full rounded-md border-slate-300 text-sm';
@endphp

<x-app-layout>
    <x-slot name="title">{{ $edit ? 'Ubah Arsip' : 'Arsipkan Surat Masuk' }}</x-slot>
    <x-slot name="lebar">max-w-[100rem]</x-slot>
    <x-slot name="header"><h1 class="text-xl font-semibold text-slate-900">{{ $edit ? 'Ubah Arsip Surat Masuk' : 'Arsipkan Surat Masuk' }}</h1></x-slot>

    <form method="post" enctype="multipart/form-data" action="{{ $edit ? route('surat-masuk.update', $surat) : route('surat-masuk.store') }}"
          x-data="suratMasukForm(@js([
              'nilai' => $nilai,
              'kategori' => $daftarKategori,
              'basis' => asset('vendor/ocr'),
              'pratinjau' => $edit ? route('surat-masuk.berkas', $surat) : null,
              'tipePratinjau' => $edit ? ($surat->isImage() ? 'gambar' : 'pdf') : null,
          ]))"
          class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        @csrf
        @if ($edit) @method('put') @endif

        <div class="space-y-5">
            <section class="bg-white border border-slate-200 rounded-lg p-5">
                <h2 class="font-semibold">1. Berkas scan</h2>
                <input id="file_scan" name="file_scan" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/png,image/jpeg"
                       @change="pilihBerkas($event)" @required(! $edit)
                       class="mt-3 block w-full text-sm" aria-describedby="info-ocr">
                <p class="mt-1 text-xs text-slate-600">{{ $edit ? 'Pilih berkas baru hanya jika ingin mengganti. ' : '' }}PDF, JPG, atau PNG, maks. 10 MB.</p>

                <div id="info-ocr" class="mt-3 text-sm" aria-live="polite">
                    <p x-show="status === 'diam'" class="text-slate-600">
                        Setelah berkas dipilih, nomor surat, tanggal, pengirim, dan perihal dibaca otomatis. Pemrosesan berlangsung di browser Anda; berkas tidak dikirim ke layanan lain.
                    </p>

                    <div x-show="status === 'membaca'" x-cloak>
                        <div class="flex justify-between text-slate-700"><span x-text="pesan"></span><span x-text="persen + '%'"></span></div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="persen">
                            <div class="h-full bg-indigo-800 transition-all" :style="`width: ${persen}%`"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">Pembacaan pertama dapat memakan waktu lebih lama karena data bahasa dimuat.</p>
                    </div>

                    <div x-show="status === 'selesai'" x-cloak class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">
                        <p>
                            Selesai dibaca (<span x-text="metode"></span>). <strong x-text="jumlahTerdeteksi"></strong> dari 5 kolom berhasil ditebak.
                            Kolom bertanda <em>Terisi dari OCR</em> perlu Anda periksa dengan berkas di sebelah.
                        </p>
                        <p x-show="jumlahTerdeteksi === 0" class="mt-1">Tidak ada data yang terbaca. Isi kolom secara manual atau coba pindai ulang dengan kualitas lebih baik.</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-sm">
                            <button type="button" @click="baca()" class="underline">Baca ulang</button>
                            <button type="button" @click="terapkan(true)" x-show="jumlahTerdeteksi > 0" class="underline">Isi ulang semua kolom dari hasil ini</button>
                        </div>
                    </div>

                    <div x-show="status === 'gagal'" x-cloak class="rounded-md border border-red-200 bg-red-50 p-3 text-red-800">
                        <p>Gagal membaca berkas: <span x-text="pesan"></span></p>
                        <p class="mt-1">Anda tetap dapat mengisi data secara manual.</p>
                        <button type="button" @click="baca()" class="mt-1 underline">Coba lagi</button>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-5 grid gap-4 sm:grid-cols-2">
                <h2 class="sm:col-span-2 font-semibold">2. Data surat</h2>

                @if ($edit)
                    <div class="sm:col-span-2 text-sm text-slate-600">Nomor agenda: <span class="font-mono">{{ $surat->nomor_agenda }}</span></div>
                @endif

                <div>
                    <x-input-label for="nomor_surat_asal" value="Nomor surat asal" />
                    <input id="nomor_surat_asal" name="nomor_surat_asal" x-model="f.nomor_surat_asal" @input="sentuh('nomor_surat_asal')" required
                           class="{{ $kelasInput }}" :class="dariOcr.nomor_surat_asal && 'border-amber-500 ring-1 ring-amber-400'">
                    <p x-show="dariOcr.nomor_surat_asal" x-cloak class="mt-1 text-xs text-amber-800">Terisi dari OCR, mohon periksa.</p>
                </div>
                <div>
                    <x-input-label for="pengirim" value="Pengirim / asal instansi" />
                    <input id="pengirim" name="pengirim" x-model="f.pengirim" @input="sentuh('pengirim')" required
                           class="{{ $kelasInput }}" :class="dariOcr.pengirim && 'border-amber-500 ring-1 ring-amber-400'">
                    <p x-show="dariOcr.pengirim" x-cloak class="mt-1 text-xs text-amber-800">Terisi dari OCR, mohon periksa.</p>
                </div>
                <div>
                    <x-input-label for="tanggal_surat" value="Tanggal surat" />
                    <input id="tanggal_surat" name="tanggal_surat" type="date" x-model="f.tanggal_surat" @input="sentuh('tanggal_surat')" required
                           class="{{ $kelasInput }}" :class="dariOcr.tanggal_surat && 'border-amber-500 ring-1 ring-amber-400'">
                    <p x-show="dariOcr.tanggal_surat" x-cloak class="mt-1 text-xs text-amber-800">Terisi dari OCR, mohon periksa.</p>
                </div>
                <div>
                    <x-input-label for="tanggal_diterima" value="Tanggal diterima" />
                    <input id="tanggal_diterima" name="tanggal_diterima" type="date" x-model="f.tanggal_diterima" required class="{{ $kelasInput }}">
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="perihal" value="Perihal / ringkasan isi" />
                    <input id="perihal" name="perihal" x-model="f.perihal" @input="sentuh('perihal')" required
                           class="{{ $kelasInput }}" :class="dariOcr.perihal && 'border-amber-500 ring-1 ring-amber-400'">
                    <p x-show="dariOcr.perihal" x-cloak class="mt-1 text-xs text-amber-800">Terisi dari OCR, mohon periksa.</p>
                </div>
                <div>
                    <x-input-label for="kategori" value="Kategori" />
                    <select id="kategori" name="kategori" x-model="f.kategori" @change="sentuh('kategori')" required
                            class="{{ $kelasInput }}" :class="dariOcr.kategori && 'border-amber-500 ring-1 ring-amber-400'">
                        @foreach ($daftarKategori as $k)
                            <option value="{{ $k }}">{{ $k }}</option>
                        @endforeach
                    </select>
                    <p x-show="dariOcr.kategori" x-cloak class="mt-1 text-xs text-amber-800">Terisi dari OCR, mohon periksa.</p>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="keterangan" value="Keterangan (opsional)" />
                    <textarea id="keterangan" name="keterangan" x-model="f.keterangan" rows="3" class="{{ $kelasInput }}"></textarea>
                </div>
            </section>

            <div class="flex gap-3">
                <x-primary-button>Simpan arsip</x-primary-button>
                <a href="{{ $edit ? route('surat-masuk.show', $surat) : route('surat-masuk.index') }}" class="self-center text-sm text-slate-600 underline">Batal</a>
            </div>
        </div>

        <div class="xl:sticky xl:top-4 xl:self-start space-y-3">
            <div class="text-sm font-semibold">Pratinjau berkas</div>
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <template x-if="pratinjau && tipePratinjau === 'gambar'">
                    <img :src="pratinjau" alt="Pratinjau scan surat" class="w-full">
                </template>
                <template x-if="pratinjau && tipePratinjau === 'pdf'">
                    <iframe :src="pratinjau" title="Pratinjau PDF surat" class="w-full" style="height: 75vh"></iframe>
                </template>
                <p x-show="! pratinjau" class="p-8 text-center text-sm text-slate-600">Pratinjau berkas muncul di sini setelah Anda memilih berkas scan, supaya data dapat dicocokkan dengan aslinya.</p>
            </div>

            <details x-show="teks" x-cloak class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
                <summary class="cursor-pointer font-medium">Lihat teks hasil pembacaan</summary>
                <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap text-xs text-slate-700" x-text="teks"></pre>
            </details>
        </div>
    </form>
</x-app-layout>
