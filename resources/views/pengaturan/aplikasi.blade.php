<x-app-layout>
    <x-slot name="title">Pengaturan Aplikasi</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Pengaturan Aplikasi</h1>
            <p class="text-sm text-slate-600">Nama, logo, dan kategori surat masuk.</p>
        </div>
    </x-slot>

    @php $kelas = 'mt-1 block w-full rounded-md border-slate-300 text-sm'; @endphp

    <div class="max-w-3xl space-y-6">
        <form method="post" action="{{ route('pengaturan.aplikasi.update') }}" class="bg-white border border-slate-200 rounded-lg p-5 flex flex-col gap-4">
            @csrf @method('put')
            <div>
                <label for="nama_aplikasi" class="block text-sm font-medium text-slate-700">Nama aplikasi</label>
                <input id="nama_aplikasi" name="nama_aplikasi" value="{{ old('nama_aplikasi', $identitas['nama_aplikasi']) }}" required maxlength="30" class="{{ $kelas }}">
                @error('nama_aplikasi')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="nama_unit" class="block text-sm font-medium text-slate-700">Nama unit</label>
                <input id="nama_unit" name="nama_unit" value="{{ old('nama_unit', $identitas['nama_unit']) }}" required maxlength="60" aria-describedby="bantuan-unit" class="{{ $kelas }}">
                <p id="bantuan-unit" class="mt-1 text-xs text-slate-600">Tampil di menu, halaman masuk, dan halaman validasi publik.</p>
                @error('nama_unit')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="kategori_surat_masuk" class="block text-sm font-medium text-slate-700">Kategori surat masuk</label>
                <textarea id="kategori_surat_masuk" name="kategori_surat_masuk" rows="6" required maxlength="2000" aria-describedby="bantuan-kategori" class="{{ $kelas }}">{{ old('kategori_surat_masuk', implode("\n", $identitas['kategori_surat_masuk'])) }}</textarea>
                <p id="bantuan-kategori" class="mt-1 text-xs text-slate-600">Satu kategori per baris. Arsip lama tetap menyimpan kategori yang dipilih saat itu, termasuk bila kategorinya diganti atau dihapus. Tebakan otomatis dari OCR hanya mengenali Undangan, Rektorat, Fakultas, dan Instansi Luar.</p>
                @error('kategori_surat_masuk')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <button class="self-start rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan pengaturan</button>
        </form>

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-logo">
            <div>
                <h2 id="judul-logo" class="font-semibold text-slate-900">Logo</h2>
                <p class="mt-1 text-sm text-slate-600">Dipakai pada menu, halaman masuk, ikon tab browser, dan kop teks.</p>
            </div>
            <figure>
                <img src="{{ \App\Services\LogoService::url() }}" alt="Logo yang sedang dipakai" class="tema-terang h-24 w-24 rounded border border-slate-200 bg-white object-contain p-2">
                <figcaption class="mt-1 text-xs text-slate-600">{{ $logoUnggahan ? 'Logo unggahan sedang dipakai.' : 'Logo bawaan sedang dipakai.' }}</figcaption>
            </figure>
            <form method="post" action="{{ route('pengaturan.aplikasi.logo') }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <label for="logo" class="block text-sm font-medium text-slate-700">Unggah logo baru</label>
                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg" required aria-describedby="bantuan-logo" class="block w-full text-sm text-slate-700">
                <p id="bantuan-logo" class="text-xs text-slate-600">PNG atau JPG, maksimal 2 MB, mendekati persegi, minimal {{ \App\Services\LogoService::SISI_MIN }} piksel. Latar transparan dipertahankan.</p>
                @error('logo')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan logo</button>
            </form>
            @if ($logoUnggahan)
                <form method="post" action="{{ route('pengaturan.aplikasi.logo.hapus') }}" onsubmit="return confirm('Hapus logo unggahan dan kembali ke logo bawaan?')">
                    @csrf @method('delete')
                    <button class="text-sm text-red-700 underline">Kembali ke logo bawaan</button>
                </form>
            @endif
        </section>
    </div>
</x-app-layout>
