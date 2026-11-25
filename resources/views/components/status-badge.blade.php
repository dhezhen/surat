@props(['surat'])
@php
    $warna = match ($surat->status) {
        'draft' => 'bg-slate-100 text-slate-700 ring-slate-300',
        'menunggu_approval' => 'bg-amber-50 text-amber-800 ring-amber-300',
        'revisi' => 'bg-red-50 text-red-700 ring-red-300',
        'terbit' => 'bg-emerald-50 text-emerald-800 ring-emerald-300',
        default => 'bg-slate-100 text-slate-700 ring-slate-300',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap {{ $warna }}">{{ $surat->labelStatus() }}</span>
