@php
    $user = Auth::user();

    // Angka pada menu mengikuti tindakan yang menunggu peran ini.
    $menunggu = $user->isSuperAdmin() ? 0 : \App\Models\SuratKeluar::where('status', $user->isKaprodi() ? 'menunggu_approval' : 'revisi')->count();
    $menunggu = $menunggu > 0 ? $menunggu : null;
    $belumDibaca = $user->unreadNotifications()->count() ?: null;
    $identitas = \App\Support\Identitas::semua();
@endphp

{{-- Bilah atas hanya untuk layar kecil; di desktop menu selalu terlihat di sisi kiri. --}}
<div class="sticky top-0 z-30 flex h-14 items-center justify-between px-4 lg:hidden print:hidden" style="background-color: var(--nm-bg)">
    <button type="button" @click="sidebar = true" class="nm-raised-sm rounded-xl p-2.5 text-indigo-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700"
            aria-label="Buka menu navigasi" :aria-expanded="sidebar.toString()" aria-controls="menu-samping">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
    </button>
    <a href="{{ route('dashboard') }}" class="font-serif text-lg font-bold text-indigo-900">{{ $identitas['nama_aplikasi'] }}</a>
    <div class="flex items-center gap-2">
        <a href="{{ route('notifikasi.index') }}" class="nm-raised-sm relative rounded-xl p-2.5 text-indigo-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700"
           aria-label="Notifikasi{{ $belumDibaca ? ', '.$belumDibaca.' belum dibaca' : '' }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15zM10 20a2 2 0 0 0 4 0"/></svg>
            @if ($belumDibaca)
                <span class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full bg-indigo-900 px-1 text-center text-xs font-semibold leading-5 text-white" aria-hidden="true">{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
            @endif
        </a>
        <x-tombol-tema ringkas />
    </div>
</div>

<div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" @click="sidebar = false" aria-hidden="true"></div>

<aside id="menu-samping" aria-label="Menu utama"
       class="nm-raised fixed inset-y-0 left-0 z-50 flex w-72 print:hidden -translate-x-full flex-col rounded-r-2xl p-4 transition-transform duration-200 lg:inset-y-4 lg:left-4 lg:w-64 lg:translate-x-0 lg:rounded-2xl"
       :class="{ 'translate-x-0': sidebar }">

    <div class="flex items-start justify-between px-2 pb-4 pt-1">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 rounded">
            <img src="{{ \App\Services\LogoService::url() }}" alt="Logo {{ $identitas['nama_unit'] }}" class="tema-terang h-8 w-8 shrink-0 rounded-md bg-white object-contain p-0.5">
            <span class="min-w-0">
                <span class="block break-words font-serif text-2xl font-bold leading-none text-indigo-900">{{ $identitas['nama_aplikasi'] }}</span>
                <span class="mt-1.5 block text-xs leading-snug text-slate-600">Persuratan &amp; Arsip<br>{{ $identitas['nama_unit'] }}</span>
            </span>
        </a>
        <button type="button" @click="sidebar = false" class="rounded-lg p-1.5 text-slate-600 hover:text-indigo-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 lg:hidden" aria-label="Tutup menu navigasi">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>

    <nav class="flex-1 space-y-5 overflow-y-auto px-1 py-2">
        <div>
            <div class="px-3 pb-1.5 font-serif text-sm text-slate-600">Persuratan</div>
            <div class="space-y-1.5">
                <x-side-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    <x-slot name="icon"><path d="M4 11.5 12 5l8 6.5V19a1 1 0 0 1-1 1h-4v-5H9v5H5a1 1 0 0 1-1-1z"/></x-slot>
                    Dasbor
                </x-side-link>
                <x-side-link :href="route('notifikasi.index')" :active="request()->routeIs('notifikasi.*')" :badge="$belumDibaca" badge-judul="notifikasi belum dibaca">
                    <x-slot name="icon"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15zM10 20a2 2 0 0 0 4 0"/></x-slot>
                    Notifikasi
                </x-side-link>
                <x-side-link :href="route('surat-keluar.index')" :active="request()->routeIs('surat-keluar.*')" :badge="$menunggu">
                    <x-slot name="icon"><path d="M20 4 10.5 13.5M20 4l-6 16-3.5-6.5L4 10z"/></x-slot>
                    Surat Keluar
                </x-side-link>
                <x-side-link :href="route('surat-masuk.index')" :active="request()->routeIs('surat-masuk.*')">
                    <x-slot name="icon"><path d="M4 13l2.5-7h11L20 13v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1zM4 13h4.5l1 2h5l1-2H20"/></x-slot>
                    Arsip Surat Masuk
                </x-side-link>
            </div>
        </div>

        <div>
            <div class="px-3 pb-1.5 font-serif text-sm text-slate-600">Penelusuran</div>
            <x-side-link :href="route('pencarian')" :active="request()->routeIs('pencarian')">
                <x-slot name="icon"><circle cx="11" cy="11" r="6"/><path d="m20 20-4.2-4.2"/></x-slot>
                Pencarian Terpadu
            </x-side-link>
            <x-side-link :href="route('laporan')" :active="request()->routeIs('laporan')">
                <x-slot name="icon"><path d="M4 20h16M7 16v-5M12 16V7M17 16v-8"/></x-slot>
                Laporan &amp; Statistik
            </x-side-link>
        </div>

        @if ($user->isSuperAdmin())
            <div>
                <div class="px-3 pb-1.5 font-serif text-sm text-slate-600">Administrasi</div>
                <x-side-link :href="route('arsip.index')" :active="request()->routeIs('arsip.*')">
                    <x-slot name="icon"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/></x-slot>
                    Arsip Surat
                </x-side-link>
                <x-side-link :href="route('pengguna.index')" :active="request()->routeIs('pengguna.*')">
                    <x-slot name="icon"><circle cx="9" cy="8.5" r="3"/><path d="M3 19a6 6 0 0 1 12 0M16 6.5a3 3 0 0 1 0 5.5M18 19a6 6 0 0 0-2.5-4.9"/></x-slot>
                    Manajemen Pengguna
                </x-side-link>
                <x-side-link :href="route('pengaturan.surat')" :active="request()->routeIs('pengaturan.surat*')">
                    <x-slot name="icon"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM14 3v4h4M9 11h6M9 17c1.5-3 2.5 1 4-1s1.5 1 2 0"/></x-slot>
                    Pengaturan Surat
                </x-side-link>
                <x-side-link :href="route('pengaturan.aplikasi')" :active="request()->routeIs('pengaturan.aplikasi*')">
                    <x-slot name="icon"><path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/></x-slot>
                    Pengaturan Aplikasi
                </x-side-link>
                <x-side-link :href="route('pengaturan.ai')" :active="request()->routeIs('pengaturan.ai*')">
                    <x-slot name="icon"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="3"/></x-slot>
                    Pengaturan AI
                </x-side-link>
            </div>
        @endif

        @if ($user->isStaff())
            <div>
                <div class="px-3 pb-1.5 font-serif text-sm text-slate-600">Pengelolaan</div>
                <x-side-link :href="route('jenis-surat.index')" :active="request()->routeIs('jenis-surat.*')">
                    <x-slot name="icon"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM14 3v4h4M9 12h6M9 16h6"/></x-slot>
                    Jenis &amp; Template Surat
                </x-side-link>
            </div>
        @endif
    </nav>

    <div class="mt-3 space-y-2 border-t border-slate-300/70 pt-4">
        <div class="nm-inset rounded-xl px-3 py-2.5">
            <div class="truncate text-sm font-semibold text-slate-800">{{ $user->name }}</div>
            <div class="truncate text-xs text-slate-600">{{ $user->labelRole() }}</div>
        </div>
        <x-side-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
            <x-slot name="icon"><circle cx="12" cy="8.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></x-slot>
            Profil Akun
        </x-side-link>
        <x-tombol-tema class="max-lg:hidden" />
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:nm-raised-sm hover:text-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 5h4a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-4M10 16l-4-4 4-4M6 12h9"/></svg>
                Keluar
            </button>
        </form>
    </div>
</aside>
