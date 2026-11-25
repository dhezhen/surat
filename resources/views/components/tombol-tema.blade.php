@props(['ringkas' => false])

{{-- Label dan ikon menyebut tindakan yang akan dilakukan. "ringkas" menampilkan ikon saja. --}}
<button type="button" data-tombol-tema
        {{ $attributes->class([
            'items-center text-sm text-slate-700 transition hover:text-indigo-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700',
            'nm-raised-sm inline-flex h-11 w-11 justify-center rounded-xl' => $ringkas,
            'flex w-full gap-3 rounded-xl px-3 py-2.5 hover:nm-raised-sm' => ! $ringkas,
        ]) }}>
    <svg class="h-5 w-5 shrink-0 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/></svg>
    <svg class="hidden h-5 w-5 shrink-0 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>
    <span @class(['dark:hidden', 'sr-only' => $ringkas])>Mode gelap</span>
    <span @class(['hidden dark:inline', 'sr-only' => $ringkas])>Mode terang</span>
</button>
