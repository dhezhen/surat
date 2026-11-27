<x-app-layout>
    <x-slot name="title">Notifikasi</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Notifikasi</h1>
            <p class="text-sm text-slate-600">Pemberitahuan pengajuan, persetujuan, dan revisi surat keluar.</p>
        </div>
        @if (Auth::user()->unreadNotifications()->exists())
            <form method="post" action="{{ route('notifikasi.baca-semua') }}">
                @csrf
                <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Tandai semua dibaca</button>
            </form>
        @endif
    </x-slot>

    <section class="max-w-3xl bg-white border border-slate-200 rounded-lg">
        @forelse ($notifikasi as $n)
            @php
                $baru = $n->read_at === null;
                $warna = match ($n->data['peristiwa'] ?? null) {
                    'disetujui' => 'bg-emerald-50 text-emerald-800 ring-emerald-300',
                    'revisi' => 'bg-red-50 text-red-700 ring-red-300',
                    default => 'bg-amber-50 text-amber-800 ring-amber-300',
                };
                $label = match ($n->data['peristiwa'] ?? null) {
                    'disetujui' => 'Terbit',
                    'revisi' => 'Perlu revisi',
                    default => 'Menunggu persetujuan',
                };
            @endphp
            <a href="{{ route('notifikasi.buka', $n->id) }}"
               class="flex gap-4 px-5 py-4 border-b border-slate-100 last:border-0 hover:bg-slate-50 {{ $baru ? 'bg-indigo-50' : '' }}">
                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $baru ? 'bg-indigo-700' : '' }}" aria-hidden="true"></span>
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-medium text-slate-900">{{ $n->data['judul'] ?? 'Notifikasi' }}</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $warna }}">{{ $label }}</span>
                        @if ($baru)<span class="sr-only">(belum dibaca)</span>@endif
                    </span>
                    <span class="mt-1 block text-sm text-slate-700">{{ $n->data['pesan'] ?? '' }}</span>
                    @if (! empty($n->data['catatan']))
                        <span class="mt-1 block text-sm text-slate-600">Catatan: {{ \Illuminate\Support\Str::limit($n->data['catatan'], 160) }}</span>
                    @endif
                    <span class="mt-1 block text-xs text-slate-500" title="{{ $n->created_at->translatedFormat('d F Y H:i') }}">{{ $n->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <p class="px-5 py-8 text-sm text-slate-600">Belum ada notifikasi. Pemberitahuan muncul di sini saat surat diajukan, disetujui, atau diminta revisi.</p>
        @endforelse
    </section>

    <div class="mt-4 max-w-3xl">{{ $notifikasi->links() }}</div>
</x-app-layout>
