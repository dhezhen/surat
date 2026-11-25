<x-app-layout>
    <x-slot name="title">{{ $surat->perihal }}</x-slot>
    <x-slot name="header">
        <div class="min-w-0">
            <a href="{{ route('surat-keluar.index') }}" class="text-sm text-slate-600 hover:underline">← Surat Keluar</a>
            <h1 class="text-xl font-semibold text-slate-900 truncate">{{ $surat->perihal }}</h1>
        </div>
        <x-status-badge :surat="$surat" />
    </x-slot>

    @php $user = Auth::user(); @endphp

    <div class="grid gap-6 lg:grid-cols-3" x-data="{ revisi: false }">
        <div class="lg:col-span-2 order-2 lg:order-1">
            <x-kertas-pas>
                @include('surat-keluar._kertas')
            </x-kertas-pas>
        </div>

        <aside class="order-1 lg:order-2 space-y-4">
            @if ($surat->status === 'revisi' && $surat->catatan_revisi)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm">
                    <div class="font-semibold text-red-800">Catatan revisi dari Ketua Prodi</div>
                    <p class="mt-1 text-red-900 whitespace-pre-line">{{ $surat->catatan_revisi }}</p>
                </div>
            @endif

            <section class="bg-white border border-slate-200 rounded-lg p-4 text-sm space-y-2">
                <h2 class="font-semibold">Informasi surat</h2>
                <dl class="space-y-1.5">
                    <div x-data="{ ubahNomor: {{ $errors->has('nomor_surat_resmi') ? 'true' : 'false' }} }">
                        <dt class="text-slate-500">Nomor surat</dt>
                        <dd class="font-mono text-xs" x-show="! ubahNomor">{{ $surat->nomor_surat_resmi ?? \App\Services\PenomoranService::perkiraan().' (perkiraan)' }}</dd>
                        @if ($surat->isTerbit() && ($user->isStaff() || $user->isSuperAdmin()))
                            <button type="button" x-show="! ubahNomor" @click="ubahNomor = true" class="mt-1 text-xs text-indigo-800 underline">Ubah nomor</button>
                            <form x-show="ubahNomor" x-cloak method="post" action="{{ route('surat-keluar.nomor', $surat) }}" class="mt-1 space-y-2">
                                @csrf @method('put')
                                <label for="nomor_surat_resmi" class="sr-only">Nomor surat</label>
                                <input id="nomor_surat_resmi" name="nomor_surat_resmi" value="{{ old('nomor_surat_resmi', $surat->nomor_surat_resmi) }}" maxlength="100" required class="w-full rounded-md border-slate-300 font-mono text-xs">
                                @error('nomor_surat_resmi')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                                <p class="text-xs text-slate-600">Mengubah teks nomor pada surat yang sudah terbit. Nomor urut internal tidak berubah.</p>
                                <div class="flex gap-3 text-xs">
                                    <button class="rounded-md bg-indigo-800 px-3 py-1 text-white hover:bg-indigo-700">Simpan nomor</button>
                                    <button type="button" @click="ubahNomor = false" class="text-slate-700 underline">Batal</button>
                                </div>
                            </form>
                        @endif
                    </div>
                    <div><dt class="text-slate-500">Jenis</dt><dd>{{ $surat->jenisSurat->nama_surat }}</dd></div>
                    <div><dt class="text-slate-500">Tujuan</dt><dd class="whitespace-pre-line">{{ $surat->tujuan_surat }}</dd></div>
                    <div><dt class="text-slate-500">Metode tanda tangan</dt><dd>{{ \App\Models\SuratKeluar::METODE_TTD[$surat->metodeTtd($penandatangan)]['label'] }}</dd></div>
                    @if ($surat->isTerbit())
                        <div><dt class="text-slate-500">PDF di halaman validasi</dt><dd>{{ $surat->tampilPdfPublik() ? 'Ditampilkan untuk umum' : 'Tidak ditampilkan' }}</dd></div>
                    @endif
                    <div><dt class="text-slate-500">Dibuat oleh</dt><dd>{{ $surat->pembuat->name }}</dd></div>
                    @if ($surat->penyetuju)<div><dt class="text-slate-500">Disetujui oleh</dt><dd>{{ $surat->penyetuju->name }}</dd></div>@endif
                </dl>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-4 space-y-2">
                <h2 class="font-semibold text-sm">Tindakan</h2>

                @if ($surat->isTerbit())
                    @if ($surat->metodeTtd($penandatangan) === \App\Models\SuratKeluar::TTD_BASAH)
                        <p class="text-sm text-slate-600">Cetak PDF, lalu minta tanda tangan basah dan cap pada ruang yang disediakan.</p>
                    @endif
                    <a href="{{ route('surat-keluar.pdf', $surat) }}" class="block text-center rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Unduh PDF</a>
                    <a href="{{ route('surat-keluar.pdf', [$surat, 'unduh' => 0]) }}" target="_blank" class="block text-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Buka PDF di tab baru</a>
                    <a href="{{ route('validasi', $surat->uuid_validasi) }}" target="_blank" class="block text-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Lihat halaman validasi publik</a>

                    @if ($user->isKaprodi() || $user->isSuperAdmin())
                        @php $tampil = $surat->tampilPdfPublik(); @endphp
                        <form method="post" action="{{ route('surat-keluar.pdf-publik', $surat) }}"
                              @unless ($tampil) onsubmit="return confirm('Tampilkan PDF untuk umum? Siapa pun yang memegang QR atau tautannya dapat membaca seluruh isi dan lampiran surat ini.')" @endunless>
                            @csrf @method('put')
                            <input type="hidden" name="tampil_pdf_publik" value="{{ $tampil ? 0 : 1 }}">
                            <button class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">{{ $tampil ? 'Sembunyikan PDF dari publik' : 'Tampilkan PDF di halaman validasi' }}</button>
                        </form>
                    @endif
                @endif

                @if ($user->isStaff() && $surat->isEditable())
                    <form method="post" action="{{ route('surat-keluar.ajukan', $surat) }}">
                        @csrf
                        <button class="w-full rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ajukan ke Ketua Prodi</button>
                    </form>
                    <a href="{{ route('surat-keluar.edit', $surat) }}" class="block text-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Ubah surat</a>
                @endif

                @if ($user->isSuperAdmin() || ($user->isStaff() && $surat->isEditable()))
                    @php
                        $konfirmasi = $surat->isTerbit()
                            ? "Hapus surat TERBIT bernomor {$surat->nomor_surat_resmi}?\n\nNomornya tidak akan dipakai ulang dan tautan validasinya tidak lagi berlaku. Surat dapat dipulihkan dari menu Surat Terhapus."
                            : ($surat->status === 'menunggu_approval' ? 'Hapus surat yang sedang menunggu persetujuan Ketua Prodi?' : 'Hapus surat ini?');
                    @endphp
                    <form method="post" action="{{ route('surat-keluar.destroy', $surat) }}" onsubmit="return confirm(@js($konfirmasi))">
                        @csrf @method('delete')
                        <button class="w-full rounded-md border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">Hapus surat</button>
                    </form>
                @endif

                @if ($user->isKaprodi() && $surat->status === 'menunggu_approval')
                    @error('aksi')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <form method="post" action="{{ route('surat-keluar.setujui', $surat) }}" onsubmit="return confirm('Setujui dan terbitkan surat ini? Nomor surat akan dibuat dan dokumen dikunci.')" class="space-y-2">
                        @csrf
                        <x-pilihan-ttd :terpilih="old('metode_ttd', $surat->metodeTtd($user))" :kunci-digital="! $user->punyaTandaTangan()" />
                        @unless ($user->punyaTandaTangan())
                            <p class="text-xs text-slate-600">Untuk memakai TTD digital, <a href="{{ route('profile.edit') }}" class="text-indigo-800 underline">unggah gambar tanda tangan di Profil</a>.</p>
                        @endunless
                        <input type="hidden" name="tampil_pdf_publik" value="0">
                        <label class="flex items-start gap-3 rounded-md border border-slate-200 p-3 text-sm cursor-pointer">
                            <input type="checkbox" name="tampil_pdf_publik" value="1" @checked(old('tampil_pdf_publik', $surat->tampilPdfPublik())) class="mt-0.5 rounded border-slate-300 text-indigo-800 focus:ring-indigo-500">
                            <span>
                                <span class="block font-medium text-slate-900">Tampilkan PDF di halaman validasi</span>
                                <span class="block text-xs text-slate-600">Biarkan kosong bila surat memuat data pribadi atau bersifat rahasia.</span>
                            </span>
                        </label>
                        <button class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-600">Setujui &amp; Terbitkan</button>
                    </form>
                    <button type="button" @click="revisi = ! revisi" class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Kembalikan untuk revisi</button>
                    <form x-show="revisi" x-cloak method="post" action="{{ route('surat-keluar.revisi', $surat) }}" class="space-y-2">
                        @csrf
                        <label for="catatan_revisi" class="text-sm font-medium">Catatan revisi</label>
                        <textarea id="catatan_revisi" name="catatan_revisi" rows="4" required class="w-full rounded-md border-slate-300 text-sm" placeholder="Jelaskan bagian yang perlu diperbaiki">{{ old('catatan_revisi') }}</textarea>
                        <button class="w-full rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">Kirim revisi</button>
                    </form>
                @endif

                @if ($surat->status === 'menunggu_approval' && $user->isStaff())
                    <p class="text-sm text-slate-600">Surat sedang menunggu persetujuan Ketua Prodi dan tidak dapat diubah.</p>
                @endif
            </section>
        </aside>
    </div>
</x-app-layout>
