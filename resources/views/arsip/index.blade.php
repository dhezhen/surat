@use('App\Models\SuratKeluar')
@use('App\Services\SuratRenderer')
@php
    $tabUrl = fn (bool $hapus) => route('arsip.index', $hapus ? ['tab' => 'terhapus'] : []);
    $kelasTab = 'inline-flex items-center min-h-11 px-4 -mb-px border-b-2 text-sm font-semibold';
@endphp

<x-app-layout>
    <x-slot name="title">Arsip Surat</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Arsip Surat</h1>
            <p class="mt-1 text-sm text-slate-600">Semua surat keluar yang sudah terbit, dari sistem maupun buku agenda manual.</p>
        </div>
        <a href="{{ route('arsip.create') }}" class="inline-flex items-center gap-2 rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Tambah arsip
        </a>
    </x-slot>

    <div x-data="{ aksi: '', nomor: '' }">
        <nav aria-label="Tampilan arsip" class="mb-4 flex gap-1 border-b border-slate-300">
            <a href="{{ $tabUrl(false) }}" @if (! $terhapus) aria-current="page" @endif
               class="{{ $kelasTab }} {{ $terhapus ? 'border-transparent text-slate-600 hover:text-slate-900' : 'border-indigo-800 text-indigo-900' }}">Aktif</a>
            <a href="{{ $tabUrl(true) }}" @if ($terhapus) aria-current="page" @endif
               class="{{ $kelasTab }} {{ $terhapus ? 'border-indigo-800 text-indigo-900' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                Terhapus
                @if ($jumlahTerhapus)<span class="ml-2 rounded-full bg-slate-200 px-2 text-xs font-medium text-slate-700">{{ $jumlahTerhapus }}</span>@endif
            </a>
        </nav>

        <form method="get" role="search" class="mb-4 flex flex-wrap items-end gap-3">
            @if ($terhapus)<input type="hidden" name="tab" value="terhapus">@endif
            <div class="grow basis-64">
                <label for="cari-arsip" class="block text-sm font-medium text-slate-700">Cari</label>
                <input id="cari-arsip" type="search" name="q" value="{{ request('q') }}" placeholder="Nomor, perihal, atau tujuan" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label for="filter-tahun" class="block text-sm font-medium text-slate-700">Tahun</label>
                <select id="filter-tahun" name="tahun" class="mt-1 block rounded-md border-slate-300 text-sm">
                    <option value="">Semua tahun</option>
                    @foreach ($daftarTahun as $t)
                        <option value="{{ $t }}" @selected((int) request('tahun') === (int) $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-jenis" class="block text-sm font-medium text-slate-700">Jenis surat</label>
                <select id="filter-jenis" name="jenis" class="mt-1 block max-w-56 rounded-md border-slate-300 text-sm">
                    <option value="">Semua jenis</option>
                    @foreach ($daftarJenis as $j)
                        <option value="{{ $j->id }}" @selected((int) request('jenis') === $j->id)>{{ $j->nama_surat }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-sumber" class="block text-sm font-medium text-slate-700">Sumber</label>
                <select id="filter-sumber" name="sumber" class="mt-1 block rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option value="{{ SuratKeluar::SUMBER_SISTEM }}" @selected(request('sumber') === SuratKeluar::SUMBER_SISTEM)>Dari sistem</option>
                    <option value="{{ SuratKeluar::SUMBER_MANUAL }}" @selected(request('sumber') === SuratKeluar::SUMBER_MANUAL)>Arsip manual</option>
                </select>
            </div>
            <button class="rounded-md bg-indigo-800 px-4 py-2 text-sm text-white hover:bg-indigo-700">Terapkan</button>
            @if (request()->hasAny(['q', 'tahun', 'jenis', 'sumber']))
                <a href="{{ $tabUrl($terhapus) }}" class="self-center text-sm text-slate-600 underline">Reset</a>
            @endif
        </form>

        @if ($terhapus)
            <p class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Surat terhapus tidak tampil di laporan dan tautan validasinya tidak berlaku. Nomornya tetap tercatat dan tidak dipakai ulang. Pulihkan untuk mengaktifkannya kembali.
            </p>
        @endif

        <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Nomor surat</th>
                        <th scope="col" class="px-4 py-3 font-medium">Perihal &amp; tujuan</th>
                        <th scope="col" class="px-4 py-3 font-medium">Jenis</th>
                        <th scope="col" class="px-4 py-3 font-medium">{{ $terhapus ? 'Dihapus' : 'Tanggal' }}</th>
                        <th scope="col" class="px-4 py-3 font-medium">Sumber</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($surats as $s)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $s->nomor_surat_resmi }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $s->perihal }}</div>
                                <div class="text-xs text-slate-600">{{ $s->tujuan_surat }}</div>
                                @if ($terhapus && $s->alasan_hapus)
                                    <div class="mt-1 text-xs text-amber-900">Alasan: {{ $s->alasan_hapus }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $s->jenisSurat?->nama_surat }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-700">
                                @if ($terhapus)
                                    {{ SuratRenderer::tanggal($s->deleted_at) }}
                                    @if ($s->dihapusOleh)<div class="text-xs text-slate-600">oleh {{ $s->dihapusOleh->name }}</div>@endif
                                @else
                                    {{ SuratRenderer::tanggal($s->tanggal_surat) }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($s->isManual())
                                    <span class="inline-flex whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300">Arsip manual</span>
                                @else
                                    <span class="inline-flex whitespace-nowrap rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-800 ring-1 ring-inset ring-indigo-200">Dari sistem</span>
                                @endif
                            </td>
                            <td class="px-2 py-1">
                                <div class="flex justify-end gap-1">
                                    @if ($terhapus)
                                        <form method="post" action="{{ route('arsip.pulihkan', $s) }}">
                                            @csrf
                                            <button class="min-h-10 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-indigo-800 hover:bg-slate-50">Pulihkan</button>
                                        </form>
                                    @else
                                        <a href="{{ route('surat-keluar.show', $s) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-slate-700 hover:bg-slate-100" aria-label="Lihat {{ $s->nomor_surat_resmi }}" title="Lihat">
                                            <svg aria-hidden="true" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                        <a href="{{ route('arsip.edit', $s) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-indigo-800 hover:bg-indigo-50" aria-label="Ubah {{ $s->nomor_surat_resmi }}" title="Ubah">
                                            <svg aria-hidden="true" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                        </a>
                                        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-red-700 hover:bg-red-50" aria-label="Hapus {{ $s->nomor_surat_resmi }}" title="Hapus"
                                                @click="aksi = @js(route('arsip.destroy', $s)); nomor = @js($s->nomor_surat_resmi); $dispatch('open-modal', 'hapus-arsip')">
                                            <svg aria-hidden="true" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-600">
                            {{ $terhapus ? 'Tidak ada arsip yang dihapus.' : (request()->hasAny(['q', 'tahun', 'jenis', 'sumber']) ? 'Tidak ada arsip yang cocok dengan pencarian.' : 'Belum ada surat terbit.') }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $surats->links() }}</div>

        <x-modal name="hapus-arsip" max-width="lg" focusable>
            <form method="post" :action="aksi" class="p-6 space-y-4">
                @csrf @method('delete')
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Hapus arsip surat ini?</h2>
                    <p class="font-mono text-sm text-slate-600" x-text="nomor"></p>
                </div>
                <ul class="list-disc space-y-1 pl-5 text-sm text-slate-700">
                    <li>QR Code dan tautan validasinya tidak lagi berlaku.</li>
                    <li>Nomornya tetap tercatat dan tidak dipakai ulang.</li>
                    <li>Surat tidak muncul di laporan dan pencarian.</li>
                    <li>Bisa dipulihkan kapan saja dari tab Terhapus.</li>
                </ul>
                <div>
                    <label for="alasan_hapus" class="block text-sm font-medium text-slate-900">Alasan penghapusan <span class="text-red-700">*</span></label>
                    <input id="alasan_hapus" name="alasan_hapus" required maxlength="255" placeholder="Mis. surat ganda, dibatalkan penerima" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Batal</button>
                    <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">Hapus arsip</button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
