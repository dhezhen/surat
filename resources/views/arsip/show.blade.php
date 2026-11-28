{{-- Detail arsip manual: dokumennya adalah berkas PDF hasil pindai, bukan kertas yang dirender sistem. --}}
@use('App\Services\SuratRenderer')
@php $user = Auth::user(); @endphp

<x-app-layout>
    <x-slot name="title">{{ $surat->perihal }}</x-slot>
    <x-slot name="header">
        <div class="min-w-0">
            <a href="{{ $user->isSuperAdmin() ? route('arsip.index') : route('surat-keluar.index') }}" class="text-sm text-slate-600 hover:underline">← {{ $user->isSuperAdmin() ? 'Arsip Surat' : 'Surat Keluar' }}</a>
            <h1 class="text-xl font-semibold text-slate-900 truncate">{{ $surat->perihal }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300">Arsip manual</span>
            <x-status-badge :surat="$surat" />
        </div>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 order-2 lg:order-1">
            <iframe src="{{ route('surat-keluar.pdf', [$surat, 'unduh' => 0]) }}" title="Berkas surat {{ $surat->nomor_surat_resmi }}"
                    class="h-[80vh] w-full rounded-lg border border-slate-200 bg-white"></iframe>
        </div>

        <aside class="order-1 lg:order-2 space-y-4">
            <section class="bg-white border border-slate-200 rounded-lg p-4 text-sm space-y-2">
                <h2 class="font-semibold">Informasi surat</h2>
                <dl class="space-y-1.5">
                    <div><dt class="text-slate-500">Nomor surat</dt><dd class="font-mono text-xs">{{ $surat->nomor_surat_resmi }}</dd></div>
                    <div><dt class="text-slate-500">Tanggal surat</dt><dd>{{ SuratRenderer::tanggal($surat->tanggal_surat) }}</dd></div>
                    <div><dt class="text-slate-500">Jenis</dt><dd>{{ $surat->jenisSurat?->nama_surat }}</dd></div>
                    <div><dt class="text-slate-500">Tujuan</dt><dd class="whitespace-pre-line">{{ $surat->tujuan_surat }}</dd></div>
                    <div><dt class="text-slate-500">Penanda tangan</dt><dd>{{ $surat->penyetuju?->name }}</dd></div>
                    <div><dt class="text-slate-500">PDF di halaman validasi</dt><dd>{{ $surat->tampilPdfPublik() ? 'Ditampilkan untuk umum' : 'Tidak ditampilkan' }}</dd></div>
                    <div><dt class="text-slate-500">Dicatat oleh</dt><dd>{{ $surat->pembuat?->name }}</dd></div>
                    @if ($surat->keterangan_arsip && $user->isSuperAdmin())
                        <div><dt class="text-slate-500">Keterangan</dt><dd class="whitespace-pre-line">{{ $surat->keterangan_arsip }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-4 space-y-2">
                <h2 class="font-semibold text-sm">Tindakan</h2>
                <a href="{{ route('surat-keluar.pdf', $surat) }}" class="block text-center rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Unduh PDF</a>
                <a href="{{ route('validasi', $surat->uuid_validasi) }}" target="_blank" class="block text-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Lihat halaman validasi publik</a>
                @if ($user->isSuperAdmin())
                    <a href="{{ route('arsip.edit', $surat) }}" class="block text-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Ubah arsip</a>
                @endif
            </section>
        </aside>
    </div>
</x-app-layout>
