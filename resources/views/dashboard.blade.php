<x-app-layout>
    <x-slot name="title">Dasbor</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Dasbor</h1>
            <p class="text-sm text-slate-600">Selamat datang, {{ Auth::user()->name }}.</p>
        </div>
        @if (Auth::user()->isStaff())
            <div class="flex gap-2">
                <a href="{{ route('surat-keluar.create') }}" class="inline-flex items-center rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Surat Keluar</a>
                <a href="{{ route('surat-masuk.create') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">+ Arsip Surat Masuk</a>
            </div>
        @endif
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <div class="text-sm text-slate-600">Surat keluar terbit bulan ini</div>
            <div class="mt-1 text-3xl font-bold text-indigo-900">{{ $suratKeluarBulanIni }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <div class="text-sm text-slate-600">Arsip surat masuk bulan ini</div>
            <div class="mt-1 text-3xl font-bold text-indigo-900">{{ $suratMasukBulanIni }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-5 {{ $menunggu->isNotEmpty() ? 'ring-2 ring-amber-300' : '' }}">
            <div class="text-sm text-slate-600">Menunggu persetujuan Kaprodi</div>
            <div class="mt-1 text-3xl font-bold text-indigo-900">{{ $menunggu->count() }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white border border-slate-200 rounded-lg">
                <h2 class="px-5 py-3 border-b border-slate-200 font-semibold">Antrean persetujuan</h2>
                @forelse ($menunggu as $s)
                    <a href="{{ route('surat-keluar.show', $s) }}" class="flex items-center justify-between gap-4 px-5 py-3 border-b border-slate-100 last:border-0 hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ $s->perihal }}</div>
                            <div class="text-sm text-slate-600 truncate">{{ $s->jenisSurat->nama_surat }} · kepada {{ $s->tujuan_surat }} · oleh {{ $s->pembuat->name }}</div>
                        </div>
                        <span class="text-sm text-indigo-700 whitespace-nowrap">{{ Auth::user()->isKaprodi() ? 'Tinjau' : 'Lihat' }}</span>
                    </a>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-600">Tidak ada surat yang menunggu persetujuan.</p>
                @endforelse
            </section>

            @if ($perluRevisi->isNotEmpty())
                <section class="bg-white border border-red-200 rounded-lg">
                    <h2 class="px-5 py-3 border-b border-red-200 font-semibold text-red-800">Perlu revisi</h2>
                    @foreach ($perluRevisi as $s)
                        <a href="{{ route('surat-keluar.show', $s) }}" class="block px-5 py-3 border-b border-slate-100 last:border-0 hover:bg-red-50">
                            <div class="font-medium">{{ $s->perihal }}</div>
                            <div class="text-sm text-slate-600">Catatan: {{ \Illuminate\Support\Str::limit($s->catatan_revisi, 140) }}</div>
                        </a>
                    @endforeach
                </section>
            @endif

            <section class="bg-white border border-slate-200 rounded-lg">
                <h2 class="px-5 py-3 border-b border-slate-200 font-semibold">Aktivitas surat keluar terbaru</h2>
                @forelse ($terbaru as $s)
                    <a href="{{ route('surat-keluar.show', $s) }}" class="flex items-center justify-between gap-4 px-5 py-3 border-b border-slate-100 last:border-0 hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ $s->perihal }}</div>
                            <div class="text-sm text-slate-600 truncate">{{ $s->nomor_surat_resmi ?? $s->jenisSurat->nama_surat }}</div>
                        </div>
                        <x-status-badge :surat="$s" />
                    </a>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-600">
                        Belum ada surat keluar.
                        @if (Auth::user()->isStaff())
                            <a href="{{ route('surat-keluar.create') }}" class="text-indigo-800 underline">Buat surat pertama</a> untuk mulai.
                        @endif
                    </p>
                @endforelse
            </section>
        </div>

        <aside class="space-y-6">
            <section class="bg-white border border-slate-200 rounded-lg p-5">
                <h2 class="font-semibold">Pencarian cepat</h2>
                <form action="{{ route('pencarian') }}" class="mt-3 flex gap-2">
                    <input name="q" placeholder="Nomor, perihal, nama…" class="min-w-0 flex-1 rounded-md border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button class="rounded-md bg-indigo-800 px-3 text-sm text-white hover:bg-indigo-700">Cari</button>
                </form>
            </section>

            <section class="bg-white border border-slate-200 rounded-lg p-5">
                <h2 class="font-semibold">Ekspor Buku Agenda (CSV)</h2>
                <form method="get" class="mt-3 space-y-3" x-data="{ tahun: '{{ now()->year }}' }">
                    <label class="block text-sm text-slate-600">Tahun
                        <input type="number" x-model="tahun" min="2000" max="2100" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    </label>
                    <a :href="'{{ route('ekspor.surat-keluar') }}?tahun=' + tahun" class="block text-center rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Unduh agenda surat keluar</a>
                    <a :href="'{{ route('ekspor.surat-masuk') }}?tahun=' + tahun" class="block text-center rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Unduh agenda surat masuk</a>
                </form>
            </section>
        </aside>
    </div>
</x-app-layout>
