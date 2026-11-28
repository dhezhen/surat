<x-guest-layout>
    @if ($surat?->tampilPdfPublik())
        <x-slot name="lebar">sm:max-w-3xl</x-slot>
    @endif

    @if ($surat)
        <div class="text-center">
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-4 py-1.5 text-sm font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-300">DOKUMEN SAH &amp; TERVERIFIKASI</span>
            <p class="mt-3 text-sm text-slate-600">
                @if ($surat->isManual())
                    Surat ini tercatat resmi dalam arsip persuratan {{ \App\Support\Identitas::ambil('nama_unit') }}.
                @else
                    Surat ini tercatat resmi dan diterbitkan melalui sistem persuratan {{ \App\Support\Identitas::ambil('nama_unit') }}.
                @endif
            </p>
        </div>
        <dl class="mt-5 space-y-3 text-sm">
            <div><dt class="text-slate-500">Nomor surat</dt><dd class="font-mono">{{ $surat->nomor_surat_resmi }}</dd></div>
            <div><dt class="text-slate-500">Tanggal terbit</dt><dd>{{ \App\Services\SuratRenderer::tanggal($surat->tanggal_surat) }}</dd></div>
            <div><dt class="text-slate-500">Perihal</dt><dd>{{ $surat->perihal }}</dd></div>
            <div><dt class="text-slate-500">Tujuan</dt><dd>{{ $surat->tujuan_surat }}</dd></div>
            <div><dt class="text-slate-500">Penandatangan</dt><dd>{{ $surat->penyetuju?->name }}<br><span class="text-slate-600">{{ $surat->penyetuju?->jabatan }}</span></dd></div>
            <div><dt class="text-slate-500">Metode tanda tangan</dt><dd>{{ \App\Models\SuratKeluar::METODE_TTD[$surat->metodeTtd($surat->penyetuju)]['label'] }}</dd></div>
        </dl>

        @if (! $surat->tampilPdfPublik())
            <p class="mt-6 border-t border-slate-200 pt-5 text-sm text-slate-600">Isi surat ini tidak ditampilkan untuk umum. Untuk mencocokkan isinya, hubungi Sekretariat Program Studi.</p>
        @else
        <section class="mt-6 border-t border-slate-200 pt-5" aria-labelledby="judul-dokumen">
            <h2 id="judul-dokumen" class="font-semibold text-slate-900">Dokumen surat</h2>
            <p class="mt-1 text-sm text-slate-600">
                Cocokkan isi surat yang Anda terima dengan dokumen di bawah ini.
                @if (! $surat->isManual() && $surat->metodeTtd($surat->penyetuju) === \App\Models\SuratKeluar::TTD_BASAH)
                    Salinan dari sistem tidak memuat tanda tangan basah dan cap.
                @endif
            </p>
            <div class="mt-3 flex flex-wrap gap-3">
                <a href="{{ route('validasi.pdf', [$surat->uuid_validasi, 'unduh' => 1]) }}" class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Unduh PDF</a>
                <a href="{{ route('validasi.pdf', $surat->uuid_validasi) }}" target="_blank" rel="noopener" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Buka PDF di tab baru</a>
            </div>
            <div class="mt-4" data-pratinjau-pdf="{{ route('validasi.pdf', $surat->uuid_validasi) }}" data-font="{{ asset('vendor/pdfjs/standard_fonts') }}/">
                <p data-status role="status" class="text-sm text-slate-600">Memuat pratinjau surat…</p>
                <div data-halaman class="space-y-4"></div>
                <noscript><p class="text-sm text-slate-600">Pratinjau memerlukan JavaScript. Gunakan tombol Buka PDF di atas.</p></noscript>
            </div>
        </section>
        @endif
    @else
        <div class="text-center">
            <span class="inline-flex items-center rounded-full bg-red-50 px-4 py-1.5 text-sm font-semibold text-red-800 ring-1 ring-inset ring-red-300">DOKUMEN TIDAK DITEMUKAN</span>
            <p class="mt-3 text-sm text-slate-600">Kode validasi tidak cocok dengan surat terbit mana pun. Pastikan QR Code dipindai dari dokumen asli, atau hubungi Sekretariat Program Studi.</p>
        </div>
    @endif
</x-guest-layout>
