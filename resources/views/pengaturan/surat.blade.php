<x-app-layout>
    <x-slot name="title">Pengaturan Surat</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Pengaturan Surat</h1>
            <p class="text-sm text-slate-600">Perubahan berlaku untuk surat yang disetujui sesudahnya. Surat yang sudah terbit tidak berubah.</p>
        </div>
    </x-slot>

    @php $kelas = 'mt-1 block w-full rounded-md border-slate-300 text-sm'; @endphp

    <div class="max-w-3xl space-y-6">
        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-identitas">
            <div>
                <h2 id="judul-identitas" class="font-semibold text-slate-900">Identitas prodi</h2>
                <p class="mt-1 text-sm text-slate-600">Kota dan label nomor induk tercetak di blok tanda tangan. Nama institusi dan alamat tercetak bila surat memakai kop teks.</p>
            </div>
            <form method="post" action="{{ route('pengaturan.surat.identitas') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf @method('put')
                @foreach (['nama_universitas' => 'Nama universitas', 'nama_fakultas' => 'Nama fakultas', 'nama_prodi' => 'Nama program studi'] as $nama => $label)
                    <div class="sm:col-span-2">
                        <label for="{{ $nama }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                        <input id="{{ $nama }}" name="{{ $nama }}" value="{{ old($nama, $identitas[$nama]) }}" required maxlength="100" class="{{ $kelas }}">
                        @error($nama)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <label for="alamat" class="block text-sm font-medium text-slate-700">Alamat</label>
                    <textarea id="alamat" name="alamat" rows="3" required maxlength="500" aria-describedby="bantuan-alamat" class="{{ $kelas }}">{{ old('alamat', implode("\n", $identitas['alamat'])) }}</textarea>
                    <p id="bantuan-alamat" class="mt-1 text-xs text-slate-600">Satu baris per kampus atau keterangan, paling banyak 5 baris.</p>
                    @error('alamat')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="kota" class="block text-sm font-medium text-slate-700">Kota pada tanggal surat</label>
                    <input id="kota" name="kota" value="{{ old('kota', $identitas['kota']) }}" required maxlength="60" class="{{ $kelas }}">
                    @error('kota')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="label_id_penandatangan" class="block text-sm font-medium text-slate-700">Label nomor induk (NIK, NIDN, NIP)</label>
                    <input id="label_id_penandatangan" name="label_id_penandatangan" value="{{ old('label_id_penandatangan', $identitas['label_id_penandatangan']) }}" required maxlength="20" class="{{ $kelas }}">
                    @error('label_id_penandatangan')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <button class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan identitas</button>
                </div>
            </form>
        </section>

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-nomor">
            <div>
                <h2 id="judul-nomor" class="font-semibold text-slate-900">Penomoran surat</h2>
                <p class="mt-1 text-sm text-slate-600">Nomor surat yang sudah terbit tidak berubah. Nomor urut kembali ke 1 setiap tahun.</p>
            </div>
            <form method="post" action="{{ route('pengaturan.surat.penomoran') }}" class="grid gap-4 sm:grid-cols-2"
                  x-data="contohNomor(@js([
                      'format' => old('format_nomor', $identitas['format_nomor']),
                      'pad' => (int) old('nomor_pad', $identitas['nomor_pad']),
                      'fakultas' => old('kode_fakultas', $identitas['kode_fakultas']),
                      'prodi' => old('kode_prodi', $identitas['kode_prodi']),
                      'awal' => old('nomor_awal', $nomorAwal),
                  ]), {{ $minimal }}, {{ $tahun }}, {{ now()->month }})">
                @csrf @method('put')
                <div class="sm:col-span-2">
                    <label for="format_nomor" class="block text-sm font-medium text-slate-700">Format nomor</label>
                    <input id="format_nomor" name="format_nomor" x-model="format" value="{{ old('format_nomor', $identitas['format_nomor']) }}" required maxlength="80" aria-describedby="bantuan-format" class="{{ $kelas }} font-mono">
                    <p id="bantuan-format" class="mt-1 text-xs text-slate-600">Penanda: {urut} {prodi} {fakultas} {bulan} {tahun}. Bulan ditulis dengan angka Romawi. {urut} dan {tahun} wajib ada.</p>
                    @error('format_nomor')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="kode_prodi" class="block text-sm font-medium text-slate-700">Kode prodi</label>
                    <input id="kode_prodi" name="kode_prodi" x-model="prodi" value="{{ old('kode_prodi', $identitas['kode_prodi']) }}" maxlength="40" class="{{ $kelas }}">
                    @error('kode_prodi')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="kode_fakultas" class="block text-sm font-medium text-slate-700">Kode fakultas</label>
                    <input id="kode_fakultas" name="kode_fakultas" x-model="fakultas" value="{{ old('kode_fakultas', $identitas['kode_fakultas']) }}" maxlength="40" class="{{ $kelas }}">
                    @error('kode_fakultas')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="nomor_pad" class="block text-sm font-medium text-slate-700">Jumlah digit nomor urut</label>
                    <input id="nomor_pad" name="nomor_pad" type="number" min="0" max="6" x-model.number="pad" value="{{ old('nomor_pad', $identitas['nomor_pad']) }}" required aria-describedby="bantuan-pad" class="{{ $kelas }}">
                    <p id="bantuan-pad" class="mt-1 text-xs text-slate-600">0 menulis 86, sedangkan 3 menulis 086.</p>
                    @error('nomor_pad')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="nomor_awal" class="block text-sm font-medium text-slate-700">Nomor urut berikutnya ({{ $tahun }})</label>
                    <input id="nomor_awal" name="nomor_awal" type="number" min="{{ $minimal }}" max="999999" x-model="awal" value="{{ old('nomor_awal', $nomorAwal) }}" placeholder="Otomatis: {{ $minimal }}" aria-describedby="bantuan-awal" class="{{ $kelas }}">
                    <p id="bantuan-awal" class="mt-1 text-xs text-slate-600">Kosongkan agar berlanjut otomatis. Isi untuk meneruskan buku agenda manual.</p>
                    @error('nomor_awal')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <p class="sm:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    Contoh nomor berikutnya: <output class="font-mono font-semibold text-slate-900" x-text="contoh"></output>
                </p>
                <div class="sm:col-span-2">
                    <button class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan penomoran</button>
                </div>
            </form>
        </section>

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-kop">
            <div>
                <h2 id="judul-kop" class="font-semibold text-slate-900">Kop surat</h2>
                <p class="mt-1 text-sm text-slate-600">Kop gambar adalah satu gambar utuh selebar area surat. Kop teks disusun dari identitas prodi dan logo aplikasi.</p>
            </div>

            <form method="post" action="{{ route('pengaturan.surat.kop.mode') }}" class="flex flex-wrap items-end gap-3">
                @csrf @method('put')
                <div>
                    <label for="mode" class="block text-sm font-medium text-slate-700">Jenis kop</label>
                    <select id="mode" name="mode" class="{{ $kelas }}">
                        <option value="gambar" @selected(! $kopTeks)>Kop gambar</option>
                        <option value="teks" @selected($kopTeks)>Kop teks</option>
                    </select>
                </div>
                <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50">Pakai jenis ini</button>
            </form>

            @if ($kop)
                <figure>
                    <img src="{{ $kop }}" alt="Kop surat yang sedang dipakai" class="w-full rounded border border-slate-200 bg-white p-2">
                    <figcaption class="mt-1 text-xs text-slate-600">{{ $kopUnggahan ? 'Kop unggahan sedang dipakai.' : 'Kop bawaan aplikasi sedang dipakai.' }}</figcaption>
                </figure>
            @else
                <figure>
                    <div class="overflow-x-auto rounded border border-slate-200 bg-white p-3" style="font-family:'Times New Roman',Times,serif">
                        <div class="min-w-[36rem]">@include('surat-keluar._kop-teks')</div>
                    </div>
                    <figcaption class="mt-1 text-xs text-slate-600">Kop teks sedang dipakai.</figcaption>
                </figure>
            @endif

            <form method="post" action="{{ route('pengaturan.surat.kop') }}" enctype="multipart/form-data" class="space-y-2" x-data="{ mengirim: false }" @submit="mengirim = true">
                @csrf
                <label for="kop" class="block text-sm font-medium text-slate-700">Unggah kop baru</label>
                <input id="kop" name="kop" type="file" accept="image/png,image/jpeg" required aria-describedby="bantuan-kop" class="block w-full text-sm text-slate-700">
                <p id="bantuan-kop" class="text-xs text-slate-600">PNG atau JPG, maksimal 4 MB. Lebar minimal {{ \App\Services\KopSuratService::LEBAR_MIN }} piksel dan setidaknya dua kali tingginya.</p>
                @error('kop')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button :disabled="mengirim" class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" x-text="mengirim ? 'Mengunggah…' : 'Simpan kop surat'">Simpan kop surat</button>
            </form>

            @if ($kopUnggahan)
                <form method="post" action="{{ route('pengaturan.surat.kop.hapus') }}" onsubmit="return confirm('Hapus kop unggahan dan kembali ke kop bawaan?')">
                    @csrf @method('delete')
                    <button class="text-sm text-red-700 underline">Kembali ke kop bawaan</button>
                </form>
            @endif
        </section>

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-ttd">
            <div>
                <h2 id="judul-ttd" class="font-semibold text-slate-900">Tanda tangan digital Ketua Prodi</h2>
                <p class="mt-1 text-sm text-slate-600">Dipakai pada surat dengan metode TTD digital. Unggah hanya atas izin pemiliknya. Ketua Prodi juga bisa mengaturnya sendiri di menu Profil.</p>
            </div>

            @forelse ($penandatangan as ['user' => $u, 'gambar' => $gambar])
                <div class="space-y-3 border-t border-slate-200 pt-4">
                    <p class="text-sm font-medium text-slate-900">{{ $u->name }}</p>

                    @if ($gambar)
                        <img src="{{ $gambar }}" alt="Tanda tangan {{ $u->name }}" class="h-24 max-w-full rounded border border-slate-200 bg-white p-2">
                    @else
                        <p class="text-sm text-slate-700">Belum ada tanda tangan. Surat TTD digital belum bisa disetujui.</p>
                    @endif

                    <form method="post" action="{{ route('pengaturan.surat.ttd', $u) }}" enctype="multipart/form-data" class="space-y-2" x-data="{ mengirim: false }" @submit="mengirim = true">
                        @csrf
                        <label for="ttd-{{ $u->id }}" class="block text-sm font-medium text-slate-700">{{ $gambar ? 'Ganti berkas tanda tangan' : 'Unggah berkas tanda tangan' }}</label>
                        <input id="ttd-{{ $u->id }}" name="tanda_tangan" type="file" accept="image/png,image/jpeg" required aria-describedby="bantuan-ttd-{{ $u->id }}" class="block w-full text-sm text-slate-700">
                        <p id="bantuan-ttd-{{ $u->id }}" class="text-xs text-slate-600">PNG atau JPG, maksimal 4 MB. Tinta gelap di kertas putih; latar putih otomatis dibuat transparan.</p>
                        @error('tanda_tangan')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        <button :disabled="mengirim" class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" x-text="mengirim ? 'Mengunggah…' : 'Simpan tanda tangan'">Simpan tanda tangan</button>
                    </form>

                    @if ($gambar)
                        <form method="post" action="{{ route('pengaturan.surat.ttd.hapus', $u) }}" onsubmit="return confirm(@js('Hapus tanda tangan '.$u->name.'?'))">
                            @csrf @method('delete')
                            <button class="text-sm text-red-700 underline">Hapus tanda tangan</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-700">Belum ada Ketua Prodi yang aktif. <a href="{{ route('pengguna.index') }}" class="text-indigo-800 underline">Atur di Manajemen Pengguna</a>.</p>
            @endforelse
        </section>
    </div>

    @push('scripts')
    <script>
        // Meniru PenomoranService::susun agar contoh berubah selagi format diketik.
        function contohNomor(awalan, minimal, tahun, bulan) {
            const romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
            return {
                ...awalan,
                get contoh() {
                    const urut = Math.max(Number(this.awal) || minimal, minimal);
                    const nilai = {urut: String(urut).padStart(Number(this.pad) || 0, '0'), prodi: this.prodi ?? '', fakultas: this.fakultas ?? '', bulan: romawi[bulan - 1], tahun};
                    return String(this.format).replace(/\{(urut|prodi|fakultas|bulan|tahun)\}/g, (_, k) => nilai[k]);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
