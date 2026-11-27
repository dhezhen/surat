{{--
    Grafik kolom satu seri per bulan. Arahkan kursor atau fokus papan ketik ke kolom untuk melihat angkanya;
    hanya nilai tertinggi yang diberi label langsung. Angka lengkap ada di tabel rekap halaman laporan.
--}}
@props(['judul', 'nilai', 'bulan', 'tahun', 'skala', 'id'])

@php
    $total = array_sum($nilai);
    $tertinggi = max($nilai);
    $indeksTertinggi = $tertinggi > 0 ? array_search($tertinggi, $nilai, true) : null;
    $garis = [0, $skala / 4, $skala / 2, $skala * 3 / 4, $skala];
@endphp

<figure {{ $attributes->merge(['class' => 'bg-white border border-slate-200 rounded-lg p-5 break-inside-avoid']) }} aria-labelledby="{{ $id }}-judul">
    <figcaption>
        <h2 id="{{ $id }}-judul" class="font-semibold text-slate-900">{{ $judul }}</h2>
        <p class="text-sm text-slate-600">{{ $total }} surat sepanjang {{ $tahun }}</p>
    </figcaption>

    <div class="mt-5 flex gap-2">
        {{-- Sumbu nilai --}}
        <div class="relative h-48 w-7 shrink-0 text-right text-xs tabular-nums text-slate-500" aria-hidden="true">
            @foreach ($garis as $g)
                <span class="absolute right-0 translate-y-1/2" style="bottom: {{ $g / $skala * 100 }}%">{{ (int) $g }}</span>
            @endforeach
        </div>

        <div class="min-w-0 flex-1">
            <div class="relative h-48 border-b border-slate-400">
                @foreach (array_slice($garis, 1) as $g)
                    <div class="absolute inset-x-0 border-t border-slate-200" style="bottom: {{ $g / $skala * 100 }}%" aria-hidden="true"></div>
                @endforeach

                <ul class="absolute inset-0 flex items-stretch" role="list" aria-label="{{ $judul }} per bulan">
                    @foreach ($nilai as $i => $n)
                        @php $tinggi = $n / $skala * 100; @endphp
                        <li tabindex="0" aria-label="{{ $bulan[$i] }} {{ $tahun }}: {{ $n }} surat"
                            class="group relative flex flex-1 items-end justify-center outline-none focus-visible:bg-indigo-50">
                            @if ($n > 0)
                                <span class="block w-3/5 max-w-[24px] rounded-t" style="height: {{ $tinggi }}%; background-color: var(--grafik-batang)"></span>
                            @endif
                            @if ($i === $indeksTertinggi)
                                <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 text-xs font-semibold tabular-nums text-slate-700 group-hover:invisible group-focus-visible:invisible" style="bottom: calc({{ $tinggi }}% + 4px)" aria-hidden="true">{{ $n }}</span>
                            @endif
                            <span class="pointer-events-none absolute left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 shadow-md group-hover:block group-focus-visible:block print:!hidden"
                                  style="bottom: calc({{ $tinggi }}% + 6px)" aria-hidden="true">
                                <span class="block text-slate-600">{{ $bulan[$i] }} {{ $tahun }}</span>
                                <span class="block font-semibold tabular-nums">{{ $n }} surat</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-1.5 flex text-xs text-slate-600" aria-hidden="true">
                @foreach ($bulan as $b)
                    <span class="flex-1 text-center">{{ mb_substr($b, 0, 3) }}</span>
                @endforeach
            </div>
        </div>
    </div>
</figure>
