<x-app-layout>
    <x-slot name="title">Pencarian</x-slot>
    <x-slot name="header"><h1 class="text-xl font-semibold text-slate-900">Pencarian Terpadu</h1></x-slot>

    <form method="get" class="mb-6 flex flex-wrap gap-2">
        <input name="q" value="{{ $q }}" autofocus placeholder="Nomor surat, perihal, nama mahasiswa/dosen, pengirim…" class="w-full sm:w-96 rounded-md border-slate-300 text-sm">
        <input name="tahun" type="number" value="{{ $tahun }}" placeholder="Tahun" class="w-28 rounded-md border-slate-300 text-sm">
        <button class="rounded-md bg-indigo-800 px-5 text-sm text-white hover:bg-indigo-700">Cari</button>
    </form>

    @if (! $cari)
        <p class="text-sm text-slate-600">Masukkan kata kunci atau tahun untuk mencari di surat keluar dan arsip surat masuk.</p>
    @else
        <section class="mb-8">
            <h2 class="font-semibold mb-2">Surat keluar ({{ $keluar->count() }})</h2>
            <div class="bg-white border border-slate-200 rounded-lg divide-y divide-slate-100">
                @forelse ($keluar as $s)
                    <a href="{{ route('surat-keluar.show', $s) }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ $s->perihal }}</div>
                            <div class="text-sm text-slate-600 truncate">{{ $s->nomor_surat_resmi ?? $s->jenisSurat->nama_surat }} · kepada {{ $s->tujuan_surat }}</div>
                        </div>
                        <x-status-badge :surat="$s" />
                    </a>
                @empty
                    <p class="px-4 py-5 text-sm text-slate-600">Tidak ada surat keluar yang cocok.</p>
                @endforelse
            </div>
        </section>

        <section>
            <h2 class="font-semibold mb-2">Arsip surat masuk ({{ $masuk->count() }})</h2>
            <div class="bg-white border border-slate-200 rounded-lg divide-y divide-slate-100">
                @forelse ($masuk as $s)
                    <a href="{{ route('surat-masuk.show', $s) }}" class="block px-4 py-3 hover:bg-slate-50">
                        <div class="font-medium truncate">{{ $s->perihal }}</div>
                        <div class="text-sm text-slate-600 truncate">{{ $s->nomor_agenda }} · {{ $s->pengirim }} · {{ $s->nomor_surat_asal }}</div>
                    </a>
                @empty
                    <p class="px-4 py-5 text-sm text-slate-600">Tidak ada arsip surat masuk yang cocok.</p>
                @endforelse
            </div>
        </section>
    @endif
</x-app-layout>
