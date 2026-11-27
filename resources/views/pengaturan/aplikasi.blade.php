<x-app-layout>
    <x-slot name="title">Pengaturan Aplikasi</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Pengaturan Aplikasi</h1>
            <p class="text-sm text-slate-600">Nama, logo, kategori surat masuk, email SMTP, dan peran pengguna.</p>
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

        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-email">
            <div>
                <h2 id="judul-email" class="font-semibold text-slate-900">Email (SMTP)</h2>
                <p class="mt-1 text-sm text-slate-600">Dipakai untuk notifikasi status surat dan tautan atur ulang kata sandi. Selama belum diaktifkan, email memakai konfigurasi MAIL_* di berkas .env.</p>
            </div>

            @error('uji', 'email')<div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Uji kirim gagal: {{ $message }}</div>@enderror

            <form method="post" action="{{ route('pengaturan.aplikasi.email') }}" autocomplete="off" class="grid gap-4 sm:grid-cols-2">
                @csrf @method('put')
                <label class="sm:col-span-2 inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $email['aktif'])) class="rounded border-slate-300 text-indigo-800">
                    Kirim email lewat server SMTP ini
                </label>
                <div>
                    <label for="smtp_host" class="block text-sm font-medium text-slate-700">Server SMTP</label>
                    <input id="smtp_host" name="host" value="{{ old('host', $email['host']) }}" maxlength="255" placeholder="smtp.gmail.com" spellcheck="false" class="{{ $kelas }}">
                    @error('host', 'email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="smtp_port" class="block text-sm font-medium text-slate-700">Port</label>
                        <input id="smtp_port" name="port" type="number" min="1" max="65535" value="{{ old('port', $email['port'] ?? 587) }}" class="{{ $kelas }}">
                        @error('port', 'email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="smtp_enkripsi" class="block text-sm font-medium text-slate-700">Enkripsi</label>
                        <select id="smtp_enkripsi" name="enkripsi" class="{{ $kelas }}">
                            @foreach (\App\Support\PengaturanEmail::ENKRIPSI as $nilai => $nama)
                                <option value="{{ $nilai }}" @selected(old('enkripsi', $email['enkripsi']) === $nilai)>{{ $nama }}</option>
                            @endforeach
                        </select>
                        @error('enkripsi', 'email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="smtp_username" class="block text-sm font-medium text-slate-700">Nama pengguna</label>
                    <input id="smtp_username" name="username" value="{{ old('username', $email['username']) }}" maxlength="255" autocomplete="off" spellcheck="false" class="{{ $kelas }}">
                    @error('username', 'email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="smtp_sandi" class="block text-sm font-medium text-slate-700">Kata sandi</label>
                    <input id="smtp_sandi" name="sandi" type="password" maxlength="255" autocomplete="new-password" aria-describedby="bantuan-sandi"
                           placeholder="{{ $sandiTersimpan ? '•••••••• (tersimpan)' : '' }}" class="{{ $kelas }}">
                    <p id="bantuan-sandi" class="mt-1 text-xs text-slate-600">{{ $sandiTersimpan ? 'Kosongkan bila tidak diganti.' : 'Untuk Gmail, pakai App Password, bukan kata sandi akun.' }} Disimpan terenkripsi.</p>
                    @if ($sandiTersimpan)
                        <label class="mt-1 inline-flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" name="hapus_sandi" value="1" class="rounded border-slate-300"> Hapus kata sandi tersimpan
                        </label>
                    @endif
                    @error('sandi', 'email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="smtp_dari_alamat" class="block text-sm font-medium text-slate-700">Alamat pengirim</label>
                    <input id="smtp_dari_alamat" name="dari_alamat" type="email" value="{{ old('dari_alamat', $email['dari_alamat']) }}" maxlength="255" placeholder="prodi@kampus.ac.id" class="{{ $kelas }}">
                    @error('dari_alamat', 'email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="smtp_dari_nama" class="block text-sm font-medium text-slate-700">Nama pengirim</label>
                    <input id="smtp_dari_nama" name="dari_nama" value="{{ old('dari_nama', $email['dari_nama']) }}" maxlength="100" placeholder="{{ $identitas['nama_aplikasi'] }}" class="{{ $kelas }}">
                    @error('dari_nama', 'email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button class="sm:col-span-2 self-start justify-self-start rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan pengaturan email</button>
            </form>

            <form method="post" action="{{ route('pengaturan.aplikasi.email.uji') }}" class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                @csrf
                <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Kirim email uji</button>
                <span class="text-sm text-slate-600">Ke {{ Auth::user()->email }}, memakai pengaturan yang sudah disimpan (boleh sebelum diaktifkan).</span>
            </form>
        </section>

        @php
            $namaBawaan = \App\Models\User::namaPeranBawaan();
            $barisGagal = old('baris');
            $tombolKecil = 'inline-flex min-h-[44px] items-center rounded-md px-2 text-sm hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700';
        @endphp
        <section class="bg-white border border-slate-200 rounded-lg p-5 space-y-4" aria-labelledby="judul-peran">
            <div>
                <h2 id="judul-peran" class="font-semibold text-slate-900">Peran pengguna</h2>
                <p class="mt-1 text-sm text-slate-600">Peran inti bisa diganti namanya saja, karena alur surat bergantung padanya. Peran tambahan, mis. "Sekretaris Jurusan", memakai hak akses salah satu peran inti dan bisa diubah atau dihapus selama belum dipakai.</p>
            </div>

            @error('hapus', 'peran')<div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">Peran</th>
                            <th scope="col" class="px-3 py-2 font-medium">Hak akses</th>
                            <th scope="col" class="px-3 py-2 font-medium">Pengguna</th>
                            <th scope="col" class="px-3 py-2"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>

                    @foreach ($namaBawaan as $kode => $nama)
                        @php
                            $kunci = "bawaan:$kode";
                            $gagal = $barisGagal === $kunci;
                        @endphp
                        <tbody class="border-t border-slate-100" x-data="{ ubah: @js($gagal) }"
                               x-effect="if (ubah) $nextTick(() => $refs.nama.focus())" @keydown.escape="ubah = false; $nextTick(() => $refs.tombol.focus())">
                            <tr>
                                <td class="px-3 py-2">
                                    <span class="font-medium text-slate-900">{{ $nama }}</span>
                                    <span class="ms-1 inline-flex rounded px-1.5 py-0.5 text-xs bg-slate-100 text-slate-700">Peran inti</span>
                                    @if ($nama !== \App\Models\User::PERAN[$kode])
                                        <div class="text-xs text-slate-600">Nama asli: {{ \App\Models\User::PERAN[$kode] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600">Aturan sendiri</td>
                                <td class="px-3 py-2 text-slate-600">{{ $jumlahBawaan[$kode] ?? 0 }}</td>
                                <td class="px-3 py-1 text-right whitespace-nowrap">
                                    <button type="button" x-ref="tombol" @click="ubah = ! ubah" :aria-expanded="ubah" aria-controls="ubah-{{ $kode }}" class="{{ $tombolKecil }} text-indigo-800">Ganti nama</button>
                                </td>
                            </tr>
                            <tr id="ubah-{{ $kode }}" x-show="ubah" @unless ($gagal) x-cloak @endunless class="bg-slate-50">
                                <td colspan="4" class="px-3 py-3">
                                    <form method="post" action="{{ route('pengaturan.aplikasi.peran.bawaan', $kode) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf @method('put')
                                        <input type="hidden" name="baris" value="{{ $kunci }}">
                                        <div class="min-w-0 grow basis-56">
                                            <label for="nama_{{ $kode }}" class="block text-xs font-medium text-slate-700">Nama tampil {{ \App\Models\User::PERAN[$kode] }}</label>
                                            <input id="nama_{{ $kode }}" x-ref="nama" name="nama" value="{{ $gagal ? old('nama') : $nama }}" required maxlength="50" class="{{ $kelas }}"
                                                   @if ($gagal && $errors->peran->has('nama')) aria-invalid="true" aria-describedby="galat-{{ $kode }}" @endif>
                                        </div>
                                        <button class="min-h-[44px] rounded-md bg-indigo-800 px-4 text-sm font-medium text-white hover:bg-indigo-700">Simpan nama</button>
                                        <button type="button" @click="ubah = false; $nextTick(() => $refs.tombol.focus())" class="{{ $tombolKecil }} text-slate-700">Batal</button>
                                    </form>
                                    @if ($gagal)
                                        @error('nama', 'peran')<p id="galat-{{ $kode }}" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                    @endif
                                    <p class="mt-1 text-xs text-slate-600">Hanya nama yang berubah. Isi "{{ \App\Models\User::PERAN[$kode] }}" untuk kembali ke nama asli.</p>
                                </td>
                            </tr>
                        </tbody>
                    @endforeach

                    @forelse ($perans as $peran)
                        @php
                            $kunci = "peran:{$peran->id}";
                            $gagal = $barisGagal === $kunci;
                            $dipakai = $peran->pengguna_count;
                        @endphp
                        <tbody class="border-t border-slate-100" x-data="{ ubah: @js($gagal) }"
                               x-effect="if (ubah) $nextTick(() => $refs.nama.focus())" @keydown.escape="ubah = false; $nextTick(() => $refs.tombol.focus())">
                            <tr>
                                <td class="px-3 py-2">
                                    <span class="font-medium text-slate-900">{{ $peran->nama }}</span>
                                    <span class="ms-1 inline-flex rounded px-1.5 py-0.5 text-xs bg-indigo-50 text-indigo-800">Tambahan</span>
                                </td>
                                <td class="px-3 py-2 text-slate-600">Seperti {{ $peran->labelHakAkses() }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $dipakai }}</td>
                                <td class="px-3 py-1 text-right whitespace-nowrap">
                                    <button type="button" x-ref="tombol" @click="ubah = ! ubah" :aria-expanded="ubah" aria-controls="ubah-peran-{{ $peran->id }}" class="{{ $tombolKecil }} text-indigo-800">Ubah</button>
                                    @if ($dipakai === 0)
                                        <form method="post" action="{{ route('pengaturan.aplikasi.peran.destroy', $peran) }}" class="inline" onsubmit="return confirm(@js('Hapus peran '.$peran->nama.'? Tindakan ini tidak dapat dibatalkan.'))">
                                            @csrf @method('delete')
                                            <button class="{{ $tombolKecil }} text-red-700">Hapus</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            <tr id="ubah-peran-{{ $peran->id }}" x-show="ubah" @unless ($gagal) x-cloak @endunless class="bg-slate-50">
                                <td colspan="4" class="px-3 py-3">
                                    <form method="post" action="{{ route('pengaturan.aplikasi.peran.update', $peran) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf @method('put')
                                        <input type="hidden" name="baris" value="{{ $kunci }}">
                                        <div class="min-w-0 grow basis-56">
                                            <label for="peran_nama_{{ $peran->id }}" class="block text-xs font-medium text-slate-700">Nama peran</label>
                                            <input id="peran_nama_{{ $peran->id }}" x-ref="nama" name="nama" value="{{ $gagal ? old('nama') : $peran->nama }}" required maxlength="50" class="{{ $kelas }}">
                                        </div>
                                        <div class="min-w-0 grow basis-48">
                                            <label for="peran_akses_{{ $peran->id }}" class="block text-xs font-medium text-slate-700">Hak akses</label>
                                            @if ($dipakai > 0)
                                                <select id="peran_akses_{{ $peran->id }}" class="{{ $kelas }}" disabled aria-describedby="kunci-peran-{{ $peran->id }}">
                                                    <option>{{ $peran->labelHakAkses() }}</option>
                                                </select>
                                                <input type="hidden" name="hak_akses" value="{{ $peran->hak_akses }}">
                                            @else
                                                <select id="peran_akses_{{ $peran->id }}" name="hak_akses" class="{{ $kelas }}">
                                                    @foreach ($namaBawaan as $kodeAkses => $namaAkses)
                                                        <option value="{{ $kodeAkses }}" @selected(($gagal ? old('hak_akses') : $peran->hak_akses) === $kodeAkses)>{{ $namaAkses }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                        <button class="min-h-[44px] rounded-md bg-indigo-800 px-4 text-sm font-medium text-white hover:bg-indigo-700">Simpan perubahan</button>
                                        <button type="button" @click="ubah = false; $nextTick(() => $refs.tombol.focus())" class="{{ $tombolKecil }} text-slate-700">Batal</button>
                                    </form>
                                    @if ($gagal)
                                        @foreach (['nama', 'hak_akses'] as $isian)
                                            @error($isian, 'peran')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                        @endforeach
                                    @endif
                                    @if ($dipakai > 0)
                                        <p id="kunci-peran-{{ $peran->id }}" class="mt-1 text-xs text-slate-600">Dipakai {{ $dipakai }} pengguna, jadi hak akses terkunci dan peran tidak bisa dihapus. Pindahkan penggunanya lewat <a href="{{ route('pengguna.index') }}" class="underline">Manajemen Pengguna</a> terlebih dahulu.</p>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody class="border-t border-slate-100">
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-sm text-slate-600">Belum ada peran tambahan. Isi form di bawah untuk membuat yang pertama.</td>
                            </tr>
                        </tbody>
                    @endforelse
                </table>
            </div>

            <form method="post" action="{{ route('pengaturan.aplikasi.peran.store') }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end" aria-labelledby="judul-peran-baru">
                @csrf
                <h3 id="judul-peran-baru" class="sr-only">Tambah peran</h3>
                <div>
                    <label for="nama_peran" class="block text-sm font-medium text-slate-700">Nama peran baru</label>
                    <input id="nama_peran" name="nama_peran" value="{{ old('nama_peran') }}" required maxlength="50" placeholder="mis. Sekretaris Jurusan" class="{{ $kelas }}">
                    @error('nama_peran', 'peranBaru')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="hak_akses_peran" class="block text-sm font-medium text-slate-700">Hak akses seperti</label>
                    <select id="hak_akses_peran" name="hak_akses_peran" class="{{ $kelas }}">
                        @foreach ($namaBawaan as $kode => $nama)
                            <option value="{{ $kode }}" @selected(old('hak_akses_peran', \App\Models\User::ROLE_STAFF) === $kode)>{{ $nama }}</option>
                        @endforeach
                    </select>
                    @error('hak_akses_peran', 'peranBaru')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button class="min-h-[44px] rounded-md bg-indigo-800 px-4 text-sm font-medium text-white hover:bg-indigo-700">Tambah peran</button>
            </form>
            <p class="text-xs text-slate-600">Aturan peran inti tetap berlaku: hanya satu pengguna berhak akses Ketua Prodi yang boleh aktif karena namanya dipakai pada tanda tangan surat.</p>
        </section>
    </div>
</x-app-layout>
