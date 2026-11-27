@use('App\Http\Controllers\LaporanController')

<x-app-layout>
    <x-slot name="title">Laporan {{ $tahun }}</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Laporan &amp; Statistik {{ $tahun }}</h1>
            <p class="text-sm text-slate-600">{{ \App\Support\Identitas::ambil('nama_unit') }} · rekap surat keluar terbit dan surat masuk yang diarsipkan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 print:hidden">
            <form method="get" action="{{ route('laporan') }}" class="flex items-center gap-2">
                <label for="tahun" class="text-sm text-slate-700">Tahun</label>
                <select id="tahun" name="tahun" onchange="this.form.submit()" class="rounded-md border-slate-300 py-1.5 text-sm">
                    @foreach ($daftarTahun as $t)
                        <option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>
                    @endforeach
                </select>
                <noscript><button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">Tampilkan</button></noscript>
            </form>
            <button type="button" onclick="window.print()" class="rounded-md bg-indigo-800 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Cetak / simpan PDF</button>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="hidden text-xs text-slate-600 print:block">Dicetak {{ now()->translatedFormat('d F Y H:i') }} oleh {{ Auth::user()->name }}.</p>

        {{-- Angka utama --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="bg-white border border-slate-200 rounded-lg p-5 break-inside-avoid">
                <div class="text-sm text-slate-600">Surat keluar terbit</div>
                <div class="mt-1 text-3xl font-bold tabular-nums text-indigo-900">{{ $totalKeluar }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-5 break-inside-avoid">
                <div class="text-sm text-slate-600">Surat masuk diarsipkan</div>
                <div class="mt-1 text-3xl font-bold tabular-nums text-indigo-900">{{ $totalMasuk }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-5 break-inside-avoid">
                <div class="text-sm text-slate-600">Rata-rata lama persetujuan</div>
                <div class="mt-1 text-3xl font-bold tabular-nums text-indigo-900">{{ LaporanController::formatDurasi($persetujuan['rata']) }}</div>
                <div class="mt-1 text-xs text-slate-600">
                    @if ($persetujuan['jumlah'])
                        Median {{ LaporanController::formatDurasi($persetujuan['median']) }} · dari {{ $persetujuan['jumlah'] }} surat
                    @else
                        Belum ada data. Dihitung dari surat yang diajukan sejak fitur laporan aktif.
                    @endif
                </div>
            </div>
        </div>

        {{-- Tren bulanan: dua grafik terpisah dengan skala yang sama agar mudah dibandingkan. --}}
        <div class="grid gap-6 lg:grid-cols-2 print:grid-cols-2">
            <x-grafik-kolom id="grafik-keluar" judul="Surat keluar terbit per bulan" :nilai="$perBulanKeluar" :bulan="$bulan" :tahun="$tahun" :skala="$skala" />
            <x-grafik-kolom id="grafik-masuk" judul="Surat masuk per bulan" :nilai="$perBulanMasuk" :bulan="$bulan" :tahun="$tahun" :skala="$skala" />
        </div>

        {{-- Tabel rekap bulanan: angka lengkap di balik grafik. --}}
        <section class="bg-white border border-slate-200 rounded-lg break-inside-avoid" aria-labelledby="judul-rekap-bulanan">
            <h2 id="judul-rekap-bulanan" class="px-5 py-3 border-b border-slate-200 font-semibold">Rekap per bulan</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm tabular-nums">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium">Bulan</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Surat keluar</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium">Surat masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bulan as $i => $b)
                            <tr class="border-t border-slate-100">
                                <th scope="row" class="px-4 py-1.5 text-left font-normal text-slate-700">{{ $b }}</th>
                                <td class="px-4 py-1.5 text-right">{{ $perBulanKeluar[$i] }}</td>
                                <td class="px-4 py-1.5 text-right">{{ $perBulanMasuk[$i] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-300 font-semibold">
                            <th scope="row" class="px-4 py-2 text-left">Jumlah</th>
                            <td class="px-4 py-2 text-right">{{ $totalKeluar }}</td>
                            <td class="px-4 py-2 text-right">{{ $totalMasuk }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2 print:grid-cols-2">
            @foreach ([
                ['id' => 'jenis', 'judul' => 'Surat keluar per jenis', 'data' => $perJenis, 'kosong' => 'Belum ada surat keluar terbit pada tahun ini.'],
                ['id' => 'kategori', 'judul' => 'Surat masuk per kategori', 'data' => $perKategori, 'kosong' => 'Belum ada surat masuk pada tahun ini.'],
            ] as $blok)
                <section class="bg-white border border-slate-200 rounded-lg p-5 break-inside-avoid" aria-labelledby="judul-{{ $blok['id'] }}">
                    <h2 id="judul-{{ $blok['id'] }}" class="font-semibold text-slate-900">{{ $blok['judul'] }}</h2>
                    @if ($blok['data'])
                        @php $puncak = $blok['data'][0]['jumlah']; @endphp
                        <ul class="mt-4 space-y-3">
                            @foreach ($blok['data'] as $baris)
                                <li>
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="min-w-0 break-words text-slate-800">{{ $baris['nama'] }}</span>
                                        <span class="shrink-0 tabular-nums text-slate-700"><span class="font-semibold">{{ $baris['jumlah'] }}</span> <span class="text-slate-500">({{ str_replace('.', ',', $baris['persen']) }}%)</span></span>
                                    </div>
                                    <div class="mt-1 h-2.5" aria-hidden="true">
                                        <div class="h-full rounded-r" style="width: {{ $baris['jumlah'] / $puncak * 100 }}%; background-color: var(--grafik-batang)"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-slate-600">{{ $blok['kosong'] }}</p>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($persetujuan['jumlah'])
            <section class="bg-white border border-slate-200 rounded-lg p-5 break-inside-avoid" aria-labelledby="judul-persetujuan">
                <h2 id="judul-persetujuan" class="font-semibold text-slate-900">Lama persetujuan</h2>
                <p class="mt-1 text-sm text-slate-600">Waktu sejak surat terakhir diajukan hingga disetujui Ketua Prodi. Bila surat sempat direvisi, dihitung dari pengajuan ulang terakhir.</p>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    @foreach (['Rata-rata' => 'rata', 'Median' => 'median', 'Tercepat' => 'tercepat', 'Terlama' => 'terlama'] as $label => $kunci)
                        <div>
                            <dt class="text-slate-600">{{ $label }}</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900">{{ LaporanController::formatDurasi($persetujuan[$kunci]) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
    </div>
</x-app-layout>
