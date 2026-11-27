<x-app-layout>
    <x-slot name="title">Surat Masuk</x-slot>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-slate-900">Arsip Surat Masuk</h1>
        @if (Auth::user()->isStaff())
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('surat-masuk.pindai') }}" class="inline-flex min-h-[44px] items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-800 hover:bg-slate-50">Pindai banyak surat</a>
                <a href="{{ route('surat-masuk.create') }}" class="inline-flex min-h-[44px] items-center rounded-md bg-indigo-800 px-4 text-sm font-medium text-white hover:bg-indigo-700">+ Arsipkan Surat</a>
            </div>
        @endif
    </x-slot>

    <form method="get" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Cari agenda, nomor asal, pengirim, perihal…" class="w-full sm:w-72 rounded-md border-slate-300 text-sm">
        <select name="kategori" class="rounded-md border-slate-300 text-sm">
            <option value="">Semua kategori</option>
            @foreach (\App\Models\SuratMasuk::kategoriUntukSaring() as $k)
                <option @selected(request('kategori') === $k)>{{ $k }}</option>
            @endforeach
        </select>
        <input name="tahun" type="number" value="{{ request('tahun') }}" placeholder="Tahun" class="w-28 rounded-md border-slate-300 text-sm">
        <button class="rounded-md bg-indigo-800 px-4 text-sm text-white hover:bg-indigo-700">Terapkan</button>
        @if (request()->hasAny(['q', 'kategori', 'tahun']))
            <a href="{{ route('surat-masuk.index') }}" class="self-center text-sm text-slate-600 underline">Reset</a>
        @endif
        <a href="{{ route('ekspor.surat-masuk', request()->only('tahun')) }}" class="ms-auto self-center text-sm text-indigo-800 underline">Ekspor CSV</a>
    </form>

    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">No. Agenda</th>
                    <th class="px-4 py-3 font-medium">Pengirim</th>
                    <th class="px-4 py-3 font-medium">Perihal</th>
                    <th class="px-4 py-3 font-medium">No. Surat Asal</th>
                    <th class="px-4 py-3 font-medium">Diterima</th>
                    <th class="px-4 py-3 font-medium">Kategori</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($surats as $s)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ $s->nomor_agenda }}</td>
                        <td class="px-4 py-3">{{ $s->pengirim }}</td>
                        <td class="px-4 py-3"><a href="{{ route('surat-masuk.show', $s) }}" class="font-medium text-indigo-800 hover:underline">{{ $s->perihal }}</a></td>
                        <td class="px-4 py-3">{{ $s->nomor_surat_asal }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ \App\Services\SuratRenderer::tanggal($s->tanggal_diterima) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs">{{ $s->kategori }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-600">
                        @if (request()->hasAny(['q', 'kategori', 'tahun']))
                            Tidak ada arsip yang cocok dengan filter ini. <a href="{{ route('surat-masuk.index') }}" class="text-indigo-800 underline">Hapus filter</a>.
                        @elseif (Auth::user()->isStaff())
                            Belum ada arsip. <a href="{{ route('surat-masuk.create') }}" class="text-indigo-800 underline">Arsipkan surat masuk pertama</a>.
                        @else
                            Belum ada surat masuk yang diarsipkan.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $surats->links() }}</div>
</x-app-layout>
