{{-- Kertas A4 yang otomatis diskalakan agar muat utuh di lebar kolom. --}}
<div x-data="kertasPas()" class="w-full" :style="`height: ${tinggi}px`">
    <div x-ref="kertas" class="tema-terang bg-white shadow border border-slate-200"
         :style="`width: 21cm; min-height: 29.7cm; padding: 2cm 2.5cm 2.5cm 3cm; transform: scale(${skala}); transform-origin: top left`">
        {{ $slot }}
    </div>
</div>
