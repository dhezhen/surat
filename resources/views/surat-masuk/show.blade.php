<x-app-layout>
    <x-slot name="title">{{ $surat->nomor_agenda }}</x-slot>
    <x-slot name="header">
        <div class="min-w-0">
            <a href="{{ route('surat-masuk.index') }}" class="text-sm text-slate-600 hover:underline">← Arsip Surat Masuk</a>
            <h1 class="text-xl font-semibold text-slate-900 truncate">{{ $surat->perihal }}</h1>
        </div>
        <span class="font-mono text-sm rounded bg-slate-100 px-2 py-1">{{ $surat->nomor_agenda }}</span>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 order-2 lg:order-1 bg-white border border-slate-200 rounded-lg overflow-hidden">
            @if ($surat->isImage())
                <img src="{{ route('surat-masuk.berkas', $surat) }}" alt="Scan surat {{ $surat->nomor_surat_asal }}" class="w-full">
            @else
                <iframe src="{{ route('surat-masuk.berkas', $surat) }}" title="Pratinjau surat {{ $surat->nomor_surat_asal }}" class="w-full" style="height:80vh"></iframe>
            @endif
        </div>

        <aside class="order-1 lg:order-2 space-y-4">
            <section class="bg-white border border-slate-200 rounded-lg p-4 text-sm">
                <h2 class="font-semibold mb-2">Metadata</h2>
                <dl class="space-y-1.5">
                    <div><dt class="text-slate-500">Nomor surat asal</dt><dd>{{ $surat->nomor_surat_asal }}</dd></div>
                    <div><dt class="text-slate-500">Pengirim</dt><dd>{{ $surat->pengirim }}</dd></div>
                    <div><dt class="text-slate-500">Tanggal surat</dt><dd>{{ \App\Services\SuratRenderer::tanggal($surat->tanggal_surat) }}</dd></div>
                    <div><dt class="text-slate-500">Tanggal diterima</dt><dd>{{ \App\Services\SuratRenderer::tanggal($surat->tanggal_diterima) }}</dd></div>
                    <div><dt class="text-slate-500">Kategori</dt><dd>{{ $surat->kategori }}</dd></div>
                    @if ($surat->keterangan)<div><dt class="text-slate-500">Keterangan</dt><dd class="whitespace-pre-line">{{ $surat->keterangan }}</dd></div>@endif
                </dl>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-4 space-y-2">
                <a href="{{ route('surat-masuk.berkas', $surat) }}" target="_blank" class="block text-center rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Buka berkas di tab baru</a>
                @if (Auth::user()->isStaff())
                    <a href="{{ route('surat-masuk.edit', $surat) }}" class="block text-center rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ubah arsip</a>
                    <form method="post" action="{{ route('surat-masuk.destroy', $surat) }}" onsubmit="return confirm('Hapus arsip ini beserta berkas scan-nya?')">
                        @csrf @method('delete')
                        <button class="w-full rounded-md border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">Hapus arsip</button>
                    </form>
                @endif
            </section>
        </aside>
    </div>
</x-app-layout>
