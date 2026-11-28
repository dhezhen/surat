@use('App\Services\SuratRenderer')
@php
    $edit = $surat->exists;
    $manual = $surat->isManual();
    $kelasInput = 'mt-1 block w-full rounded-md border-slate-300 text-sm';
    $kelasLabel = 'block text-sm font-medium text-slate-900';
    $wajib = '<span class="text-red-700" aria-hidden="true">*</span>';
@endphp

<x-app-layout>
    <x-slot name="title">{{ $edit ? 'Ubah Arsip' : 'Tambah Arsip Manual' }}</x-slot>
    <x-slot name="header">
        <div class="min-w-0">
            <a href="{{ route('arsip.index') }}" class="text-sm text-slate-600 hover:underline">← Arsip Surat</a>
            <h1 class="text-xl font-semibold text-slate-900 truncate">{{ $edit ? 'Ubah arsip '.$surat->nomor_surat_resmi : 'Tambah arsip manual' }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                @if (! $edit)
                    Catat surat yang terbit di luar sistem, misalnya dari buku agenda sebelum aplikasi ini dipakai.
                @elseif ($manual)
                    Arsip manual · tanggal surat {{ SuratRenderer::tanggal($surat->tanggal_surat) }}
                @else
                    Surat dari sistem · terbit {{ SuratRenderer::tanggal($surat->tanggal_surat) }}
                @endif
            </p>
        </div>
    </x-slot>

    <form method="post" enctype="multipart/form-data" action="{{ $edit ? route('arsip.update', $surat) : route('arsip.store') }}"
          class="max-w-3xl space-y-6 rounded-lg border border-slate-200 bg-white p-6">
        @csrf
        @if ($edit) @method('put') @endif

        @unless ($manual)
            <div class="flex gap-3 rounded-md bg-indigo-50 p-4 text-sm text-indigo-900">
                <svg aria-hidden="true" class="mt-0.5 h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <p>Surat ini terbit lewat sistem, jadi isinya dibekukan dan tidak bisa diubah. Yang masih bisa diubah adalah koreksi nomor, tampilan PDF publik, dan keterangan arsip.</p>
            </div>
            <dl class="grid gap-x-6 gap-y-3 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-600">Perihal</dt><dd>{{ $surat->perihal }}</dd></div>
                <div><dt class="text-slate-600">Tujuan</dt><dd class="whitespace-pre-line">{{ $surat->tujuan_surat }}</dd></div>
                <div><dt class="text-slate-600">Jenis surat</dt><dd>{{ $surat->jenisSurat?->nama_surat }}</dd></div>
                <div><dt class="text-slate-600">Penanda tangan</dt><dd>{{ $surat->penyetuju?->name }}</dd></div>
                <div><dt class="text-slate-600">Dokumen</dt><dd><a href="{{ route('surat-keluar.show', $surat) }}" class="text-indigo-800 underline">Lihat surat</a></dd></div>
            </dl>
        @endunless

        <div>
            <label for="nomor_surat_resmi" class="{{ $kelasLabel }}">Nomor surat {!! $wajib !!}</label>
            <input id="nomor_surat_resmi" name="nomor_surat_resmi" value="{{ old('nomor_surat_resmi', $surat->nomor_surat_resmi) }}" required maxlength="100"
                   placeholder="Contoh: 087/TI-S1/FKOM/III/2024" aria-describedby="nomor-bantuan" class="{{ $kelasInput }} font-mono">
            <p id="nomor-bantuan" class="mt-1 text-xs text-slate-600">
                @if ($manual)
                    Tulis persis seperti di buku agenda. Nomor harus unik, termasuk terhadap surat yang sudah dihapus.
                @else
                    Nomor urut internal tidak berubah, jadi penomoran berikutnya tetap berlanjut. Unduh ulang PDF setelah dikoreksi.
                @endif
            </p>
            @error('nomor_surat_resmi')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        @unless ($manual)
            <div>
                <label for="alasan_koreksi" class="{{ $kelasLabel }}">Alasan koreksi nomor <span class="font-normal text-slate-600">(bila nomor diubah)</span></label>
                <input id="alasan_koreksi" name="alasan_koreksi" value="{{ old('alasan_koreksi') }}" maxlength="255" placeholder="Mis. salah ketik kode fakultas" class="{{ $kelasInput }}">
                @error('alasan_koreksi')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        @endunless

        @if ($manual)
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="tanggal_surat" class="{{ $kelasLabel }}">Tanggal surat {!! $wajib !!}</label>
                    <input id="tanggal_surat" type="date" name="tanggal_surat" max="{{ now()->toDateString() }}" required
                           value="{{ old('tanggal_surat', optional($surat->tanggal_surat)->format('Y-m-d')) }}" class="{{ $kelasInput }}">
                    @error('tanggal_surat')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="jenis_surat_id" class="{{ $kelasLabel }}">Jenis surat {!! $wajib !!}</label>
                    <select id="jenis_surat_id" name="jenis_surat_id" required class="{{ $kelasInput }}">
                        <option value="">Pilih jenis surat</option>
                        @foreach ($daftarJenis as $j)
                            <option value="{{ $j->id }}" @selected((int) old('jenis_surat_id', $surat->jenis_surat_id) === $j->id)>{{ $j->nama_surat }}</option>
                        @endforeach
                    </select>
                    @error('jenis_surat_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="perihal" class="{{ $kelasLabel }}">Perihal {!! $wajib !!}</label>
                <input id="perihal" name="perihal" value="{{ old('perihal', $surat->perihal) }}" required maxlength="255" class="{{ $kelasInput }}">
                @error('perihal')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="tujuan_surat" class="{{ $kelasLabel }}">Tujuan surat {!! $wajib !!}</label>
                    <input id="tujuan_surat" name="tujuan_surat" value="{{ old('tujuan_surat', $surat->tujuan_surat) }}" required maxlength="255" class="{{ $kelasInput }}">
                    @error('tujuan_surat')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="penyetuju_id" class="{{ $kelasLabel }}">Penanda tangan {!! $wajib !!}</label>
                    <select id="penyetuju_id" name="penyetuju_id" required class="{{ $kelasInput }}">
                        @foreach ($daftarPenandatangan as $p)
                            <option value="{{ $p->id }}" @selected((int) old('penyetuju_id', $surat->penyetuju_id) === $p->id)>{{ $p->name }}{{ $p->is_active ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </select>
                    @if ($daftarPenandatangan->isEmpty())
                        <p class="mt-1 text-sm text-red-700">Belum ada pengguna Ketua Prodi. Tambahkan di Manajemen Pengguna.</p>
                    @endif
                    @error('penyetuju_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="berkas" class="{{ $kelasLabel }}">Berkas surat (PDF) @unless ($edit){!! $wajib !!}@endunless</label>
                <input id="berkas" type="file" name="berkas" accept="application/pdf" @required(! $edit) aria-describedby="berkas-bantuan"
                       class="mt-1 block w-full rounded-md border border-dashed border-indigo-300 bg-slate-50 p-3 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-800 file:px-3 file:py-1.5 file:text-white">
                <p id="berkas-bantuan" class="mt-1 text-xs text-slate-600">
                    Hasil pindai surat bertanda tangan, PDF maks. 10 MB.
                    @if ($edit)
                        Kosongkan bila tidak mengganti. <a href="{{ route('surat-keluar.pdf', [$surat, 'unduh' => 0]) }}" target="_blank" rel="noopener" class="text-indigo-800 underline">Lihat berkas sekarang</a>
                    @endif
                </p>
                @error('berkas')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        @endif

        <div>
            <input type="hidden" name="tampil_pdf_publik" value="0">
            <label class="flex cursor-pointer items-start gap-3 text-sm">
                <input type="checkbox" name="tampil_pdf_publik" value="1" @checked(old('tampil_pdf_publik', $surat->tampilPdfPublik())) class="mt-0.5 rounded border-slate-300 text-indigo-800 focus:ring-indigo-500">
                <span>
                    <span class="block font-medium text-slate-900">Tampilkan PDF di halaman validasi publik</span>
                    <span class="block text-slate-600">Bila tidak dicentang, publik hanya melihat nomor, perihal, dan penanda tangan.</span>
                </span>
            </label>
        </div>

        <div>
            <label for="keterangan_arsip" class="{{ $kelasLabel }}">Keterangan <span class="font-normal text-slate-600">(opsional)</span></label>
            <textarea id="keterangan_arsip" name="keterangan_arsip" rows="3" maxlength="2000" placeholder="Catatan internal untuk arsip ini" class="{{ $kelasInput }}">{{ old('keterangan_arsip', $surat->keterangan_arsip) }}</textarea>
            @error('keterangan_arsip')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('arsip.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Batal</a>
            <button class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ $edit ? 'Simpan perubahan' : 'Simpan arsip' }}</button>
        </div>
    </form>
</x-app-layout>
