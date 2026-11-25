<x-app-layout>
    <x-slot name="title">Surat Terhapus</x-slot>
    <x-slot name="header">
        <div>
            <a href="{{ route('surat-keluar.index') }}" class="text-sm text-slate-600 hover:underline">← Surat Keluar</a>
            <h1 class="text-xl font-semibold text-slate-900">Surat Terhapus</h1>
        </div>
    </x-slot>

    <p class="mb-4 text-sm text-slate-600">Surat yang dihapus disimpan dan dapat dipulihkan. Nomor surat yang sudah terbit tidak dipakai ulang oleh surat lain.</p>

    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Nomor</th>
                    <th class="px-4 py-3 font-medium">Perihal</th>
                    <th class="px-4 py-3 font-medium">Status saat dihapus</th>
                    <th class="px-4 py-3 font-medium">Dihapus</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($surats as $s)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $s->nomor_surat_resmi ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $s->perihal }}</div>
                            <div class="text-xs text-slate-500">{{ $s->jenisSurat->nama_surat }} · dibuat oleh {{ $s->pembuat->name }}</div>
                        </td>
                        <td class="px-4 py-3"><x-status-badge :surat="$s" /></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ \App\Services\SuratRenderer::tanggal($s->deleted_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="post" action="{{ route('surat-keluar.pulihkan', $s) }}">
                                @csrf
                                <button class="text-indigo-800 hover:underline">Pulihkan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-600">Tidak ada surat yang dihapus.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $surats->links() }}</div>
</x-app-layout>
