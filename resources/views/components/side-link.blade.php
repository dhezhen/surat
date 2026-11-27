@props(['href', 'active' => false, 'badge' => null, 'badgeJudul' => 'menunggu tindakan'])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700
          {{ $active ? 'nm-inset font-semibold text-indigo-900' : 'text-slate-700 hover:nm-raised-sm hover:text-indigo-900' }}">
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {{ $icon }}
    </svg>
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="min-w-[1.5rem] rounded-md bg-indigo-900 px-1.5 py-0.5 text-center text-xs font-semibold text-white" title="{{ $badge }} {{ $badgeJudul }}">{{ $badge }}<span class="sr-only"> {{ $badgeJudul }}</span></span>
    @endif
</a>
