<x-app-layout>
    <x-slot name="title">Manajemen Pengguna</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Manajemen Pengguna</h1>
            <p class="text-sm text-slate-600">Akun dibuat oleh Super Admin; tidak ada pendaftaran mandiri.</p>
        </div>
        <a href="{{ route('pengguna.create') }}" class="inline-flex items-center rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Tambah Pengguna</a>
    </x-slot>

    @foreach (['status', 'hapus'] as $kunci)
        @error($kunci)<div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
    @endforeach

    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Peran</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($penggunas as $p)
                    <tr class="{{ $p->is_active ? '' : 'bg-slate-50 text-slate-500' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium {{ $p->is_active ? 'text-slate-900' : '' }}">{{ $p->name }}@if ($p->is(Auth::user())) <span class="text-xs font-normal text-slate-500">(Anda)</span>@endif</div>
                            <div class="text-xs text-slate-500">{{ $p->jabatan }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $p->email }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $p->labelRole() }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $p->is_active ? 'bg-emerald-50 text-emerald-800 ring-emerald-300' : 'bg-slate-100 text-slate-700 ring-slate-300' }}">{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('pengguna.edit', $p) }}" class="text-indigo-800 hover:underline">Ubah</a>
                            @unless ($p->is(Auth::user()))
                                <form method="post" action="{{ route('pengguna.status', $p) }}" class="inline">
                                    @csrf
                                    <button class="ms-3 {{ $p->is_active ? 'text-amber-800' : 'text-emerald-800' }} hover:underline">{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form method="post" action="{{ route('pengguna.destroy', $p) }}" class="inline" onsubmit="return confirm('Hapus pengguna {{ $p->name }}? Tindakan ini tidak dapat dibatalkan.')">
                                    @csrf @method('delete')
                                    <button class="ms-3 text-red-700 hover:underline">Hapus</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-sm text-slate-600">
        Pengguna yang sudah pernah membuat atau menyetujui surat tidak dapat dihapus; nonaktifkan agar tidak bisa masuk namun riwayat suratnya tetap utuh.
        Hanya satu Ketua Prodi yang boleh aktif karena namanya dipakai pada tanda tangan surat.
    </p>
</x-app-layout>
