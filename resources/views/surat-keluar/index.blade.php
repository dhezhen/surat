<x-app-layout>
    <x-slot name="title">Surat Keluar</x-slot>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-slate-900">Surat Keluar</h1>
        @if (Auth::user()->isStaff())
            <a href="{{ route('surat-keluar.create') }}" class="inline-flex items-center rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Buat Surat</a>
        @endif
        @if (Auth::user()->isSuperAdmin())
            <a href="{{ route('surat-keluar.terhapus') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Surat Terhapus</a>
        @endif
    </x-slot>

    <form method="get" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Cari nomor, perihal, tujuan, nama…" class="w-full sm:w-72 rounded-md border-slate-300 text-sm">
        <select name="status" class="rounded-md border-slate-300 text-sm">
            <option value="">Semua status</option>
            @foreach (['draft' => 'Draf', 'menunggu_approval' => 'Menunggu Persetujuan', 'revisi' => 'Perlu Revisi', 'terbit' => 'Terbit'] as $v => $l)
                @continue(Auth::user()->isKaprodi() && $v === 'draft')
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <input name="tahun" type="number" value="{{ request('tahun') }}" placeholder="Tahun" class="w-28 rounded-md border-slate-300 text-sm">
        <button class="rounded-md bg-indigo-800 px-4 text-sm text-white hover:bg-indigo-700">Terapkan</button>
        @if (request()->hasAny(['q', 'status', 'tahun']))
            <a href="{{ route('surat-keluar.index') }}" class="self-center text-sm text-slate-600 underline">Reset</a>
        @endif
    </form>

    <div class="relative bg-white border border-slate-200 rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Nomor</th>
                    <th class="px-4 py-3 font-medium">Perihal</th>
                    <th class="px-4 py-3 font-medium">Tujuan</th>
                    <th class="px-4 py-3 font-medium">Jenis</th>
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    @if (Auth::user()->isStaff() || Auth::user()->isSuperAdmin())<th class="px-4 py-3 font-medium"><span class="sr-only">Aksi</span></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($surats as $s)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $s->nomor_surat_resmi ?? '—' }}</td>
                        <td class="px-4 py-3"><a href="{{ route('surat-keluar.show', $s) }}" class="font-medium text-indigo-800 hover:underline">{{ $s->perihal }}</a></td>
                        <td class="px-4 py-3">{{ $s->tujuan_surat }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->jenisSurat->nama_surat }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ \App\Services\SuratRenderer::tanggal($s->tanggal_surat) }}</td>
                        <td class="px-4 py-3"><x-status-badge :surat="$s" /></td>
                        @if (Auth::user()->isStaff() || Auth::user()->isSuperAdmin())
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (Auth::user()->isSuperAdmin() || $s->isEditable())
                                    @php $tanya = $s->isTerbit() ? 'Hapus surat TERBIT bernomor '.$s->nomor_surat_resmi.'? Nomornya tidak dipakai ulang dan QR Code menjadi tidak valid. Surat dapat dipulihkan dari menu Surat Terhapus.' : 'Hapus surat ini?'; @endphp
                                    <form method="post" action="{{ route('surat-keluar.destroy', $s) }}" onsubmit="return confirm(@js($tanya))">
                                        @csrf @method('delete')
                                        <button class="text-red-700 hover:underline">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-600">
                        @if (request()->hasAny(['q', 'status', 'tahun']))
                            Tidak ada surat yang cocok dengan filter ini. <a href="{{ route('surat-keluar.index') }}" class="text-indigo-800 underline">Hapus filter</a>.
                        @elseif (Auth::user()->isStaff())
                            Belum ada surat keluar. <a href="{{ route('surat-keluar.create') }}" class="text-indigo-800 underline">Buat surat pertama</a>.
                        @else
                            Belum ada surat yang diajukan oleh Staff Prodi.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $surats->links() }}</div>
</x-app-layout>
