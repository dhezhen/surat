<x-app-layout>
    <x-slot name="title">Jenis Surat</x-slot>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-slate-900">Master Jenis Surat &amp; Template</h1>
        <a href="{{ route('jenis-surat.create') }}" class="inline-flex items-center rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Jenis Surat</a>
    </x-slot>

    @error('hapus')<div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama surat</th>
                    <th class="px-4 py-3 font-medium">Field</th>
                    <th class="px-4 py-3 font-medium">Dipakai</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($jenisSurats as $j)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $j->nama_surat }}</td>
                        <td class="px-4 py-3">{{ count($j->form_schema) }}</td>
                        <td class="px-4 py-3">{{ $j->surat_keluar_count }} surat</td>
                        <td class="px-4 py-3">{{ $j->is_active ? 'Aktif' : 'Nonaktif' }}@if ($j->pdf_publik)<span class="block text-xs text-slate-500">PDF publik</span>@endif</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('jenis-surat.edit', $j) }}" class="text-indigo-800 hover:underline">Ubah</a>
                            <form method="post" action="{{ route('jenis-surat.destroy', $j) }}" class="inline" onsubmit="return confirm('Hapus jenis surat ini?')">
                                @csrf @method('delete')
                                <button class="ms-3 text-red-700 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-600">Belum ada jenis surat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
