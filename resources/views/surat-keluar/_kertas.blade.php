{{--
    Isi kertas surat, dipakai bersama oleh tampilan layar dan PDF DomPDF (itu sebabnya memakai tabel, bukan flex/float).
    Variabel: $surat, $isi, $penandatangan, $ttd, $qr, $kop, opsional $live (pratinjau Alpine).
--}}
<style>
    .kertas-isi { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.35; color: #000; }
    .kertas-isi p { margin: 0 0 8px 0; text-align: justify; }
    .kertas-isi p.indent { text-indent: 1.25cm; }
    .kertas-isi li { text-align: justify; }
    .kertas-isi table { border-collapse: collapse; }
    .kertas-isi td { padding: 0 4px 0 0; vertical-align: top; }
    .kertas-ttd { width: 100%; margin-top: 18px; page-break-inside: avoid; }
    .kertas-ttd td { vertical-align: top; }
</style>

@php
    // Surat terbit membawa identitas yang dibekukan; selain itu dipakai pengaturan saat ini.
    $identitas ??= \App\Support\Identitas::semua();
@endphp

<div class="kertas-isi">
    @if ($kopGambar = $kop ?? \App\Services\KopSuratService::dataUri())
        <img src="{{ $kopGambar }}" alt="Kop surat" style="width:100%; margin-bottom:14px">
    @else
        @include('surat-keluar._kop-teks', ['logoKop' => $logo ?? \App\Services\LogoService::dataUri()])
    @endif

    @if (! empty($live))
        <div x-html="isi"></div>
    @else
        {!! $isi !!}
    @endif

    <table class="kertas-ttd">
        <tr>
            <td style="width:50%"></td>
            <td style="width:50%">
                <div>{{ $identitas['kota'] }},{{ ! empty($live) ? '' : \App\Services\SuratRenderer::tanggal($surat->tanggal_surat) }}@if (! empty($live))<span x-text="tanggalPanjang"></span>@endif</div>
                <div>{{ $penandatangan?->jabatan ?? 'Ketua Program Studi' }}</div>

                <div style="height:80px; margin:4px 0">
                    @if ($surat->isTerbit())
                        {{-- TTD basah: $qr dan $ttd kosong sehingga ruangnya dibiarkan untuk ditandatangani. --}}
                        @if ($qr ?? null)<img src="{{ $qr }}" style="width:76px; height:76px">@endif
                        @if ($ttd ?? null)<img src="{{ $ttd }}" style="height:76px; max-width:170px; margin-left:6px">@endif
                    @elseif (! empty($live))
                        <div style="font-size:9pt; color:#777; padding-top:30px" x-text="@js(array_map(fn ($m) => $m['pratinjau'], \App\Models\SuratKeluar::METODE_TTD))[metodeTtd]"></div>
                    @else
                        <div style="font-size:9pt; color:#777; padding-top:30px">{{ \App\Models\SuratKeluar::METODE_TTD[$surat->metodeTtd($penandatangan)]['pratinjau'] }}</div>
                    @endif
                </div>

                <div style="font-weight:bold">{{ $penandatangan?->name ?? '—' }}</div>
                @if ($penandatangan?->nidn)<div style="font-weight:bold">{{ $identitas['label_id_penandatangan'] }}. {{ $penandatangan->nidn }}</div>@endif
            </td>
        </tr>
    </table>

    {{-- Halaman lampiran: di PDF mulai di halaman baru tanpa kop; di layar dipisah garis putus-putus. --}}
    @if (! empty($live))
        <div x-show="lampiran" x-cloak style="margin-top:28px; border-top:2px dashed #94a3b8; padding-top:10px">
            <div style="font-family:Arial,sans-serif; font-size:9pt; color:#64748b; margin-bottom:8px">Halaman lampiran</div>
            <div x-html="lampiran"></div>
        </div>
    @elseif (! empty($lampiran))
        @if (! empty($pdf))
            <div style="page-break-before:always">{!! $lampiran !!}</div>
        @else
            <div style="margin-top:28px; border-top:2px dashed #94a3b8; padding-top:10px">
                <div style="font-family:Arial,sans-serif; font-size:9pt; color:#64748b; margin-bottom:8px">Halaman lampiran</div>
                {!! $lampiran !!}
            </div>
        @endif
    @endif
</div>
