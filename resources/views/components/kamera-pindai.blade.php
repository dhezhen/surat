@props(['batch' => false, 'label' => 'Pindai dengan kamera'])

{{--
    Dialog pemotret halaman surat. Hasilnya dikirim lewat event "pindai-selesai" ke elemen induk.
    Semua tombol bertipe "button" karena komponen ini bisa berada di dalam <form>.
--}}
@php
    $tombolKedua = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-800 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 disabled:opacity-50';
    $tombolUtama = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-indigo-800 px-4 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2 disabled:opacity-50';
@endphp

<div x-data="kameraPindai({ batch: @js($batch) })" {{ $attributes }}>
    <button type="button" @click="buka()" class="{{ $tombolKedua }}">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
        {{ $label }}
    </button>

    <dialog x-ref="dialog" @cancel="batal($event)" @close="bersihkan()" aria-labelledby="judul-kamera"
            class="m-0 h-full max-h-none w-full max-w-none bg-white p-0 text-slate-900 backdrop:bg-slate-900/70 sm:m-auto sm:h-auto sm:max-h-[95vh] sm:max-w-3xl sm:rounded-lg">
        <div class="flex h-full flex-col sm:max-h-[95vh]">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-2">
                <h2 id="judul-kamera" class="font-semibold" x-text="batch ? `Pindai surat ke-${jumlahDikirim + 1}` : 'Pindai surat'">Pindai surat</h2>
                <button type="button" @click="tutupDenganTanya()" class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                    <span class="sr-only">Tutup kamera</span>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                {{-- Latar hitam sengaja tetap di kedua tema: jendela bidik kamera. --}}
                <div x-show="langsung && ! galat && ! atur" class="relative bg-black">
                    <video x-ref="video" playsinline muted class="mx-auto h-[50vh] w-full object-contain sm:h-[55vh]"></video>
                    <canvas x-ref="pandu" class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true"></canvas>
                    <p x-show="siap" class="pointer-events-none absolute left-1/2 top-3 -translate-x-1/2 rounded-full bg-black/70 px-3 py-1 text-xs text-white" aria-hidden="true"
                       x-text="pandu ? 'Tepi kertas terbaca' : 'Mencari tepi kertas…'"></p>
                    {{-- Kilat putih tanda foto diambil; warna tetap, bukan warna tema. --}}
                    <div x-show="kilat" class="pointer-events-none absolute inset-0" style="background: rgb(255 255 255 / 0.7)" aria-hidden="true"></div>
                    <p x-show="menyalakan" class="absolute inset-0 flex items-center justify-center text-sm text-white">Menyalakan kamera…</p>
                </div>

                {{-- Penyunting sudut. Latar gelap tetap agar foto dan titik hijau terlihat jelas di kedua tema. --}}
                <div x-show="atur" x-cloak class="bg-slate-900 px-2 py-3" role="group" aria-labelledby="judul-atur">
                    <p id="judul-atur" class="mb-2 px-2 text-sm text-slate-200">
                        <span class="font-semibold text-white" x-text="atur ? `Atur sudut halaman ${atur.i + 1}` : ''"></span>.
                        Seret titik hijau ke sudut kertas. Dengan keyboard: pindah titik dengan Tab, geser dengan panah (tahan Shift agar lebih cepat).
                    </p>
                    <div class="flex justify-center">
                        <div x-ref="bidangAtur" class="relative touch-none select-none" @pointermove="seret($event)" @pointerup="lepasSeret()" @pointercancel="lepasSeret()">
                            <img :src="atur?.url" alt="Foto asli halaman" draggable="false" class="block max-h-[50vh] max-w-full sm:max-h-[55vh]">
                            <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1 1" preserveAspectRatio="none" aria-hidden="true">
                                <polygon :points="atur ? atur.sudut.map((p) => `${p.x},${p.y}`).join(' ') : ''"
                                         fill="rgba(34, 197, 94, 0.15)" stroke="#22c55e" stroke-width="2" vector-effect="non-scaling-stroke" />
                            </svg>
                            <template x-for="(p, k) in (atur ? atur.sudut : [])" :key="k">
                                <button type="button" :data-sudut="k"
                                        @pointerdown="mulaiSeret($event, k)" @keydown="panah($event, k)" @focus="atur.aktif = k"
                                        :style="`left: ${p.x * 100}%; top: ${p.y * 100}%`"
                                        class="absolute flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 touch-none items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                        :aria-label="`Sudut ${['kiri atas', 'kanan atas', 'kanan bawah', 'kiri bawah'][k]}, ${Math.round(p.x * 100)}% dari kiri, ${Math.round(p.y * 100)}% dari atas`">
                                    <span class="block h-5 w-5 rounded-full border-2 border-white shadow" :style="`background: ${atur.aktif === k ? '#22c55e' : 'rgb(34 197 94 / 0.55)'}`"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p x-show="atur && ! sudutSah" class="mt-2 px-2 text-sm text-amber-300" role="alert">Titik sudut bersilangan. Susun searah jarum jam mulai dari kiri atas.</p>
                    <p x-show="pesanAtur" class="mt-2 px-2 text-sm text-amber-300" role="alert" x-text="pesanAtur"></p>
                </div>

                <div x-show="(! langsung || galat) && ! atur" x-cloak class="space-y-2 px-4 py-4 text-sm">
                    <p x-show="galat" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-800" x-text="galat"></p>
                    <p x-show="! langsung" class="text-slate-700">Kamera tidak bisa dibuka langsung di halaman ini karena aplikasi belum diakses lewat HTTPS. Pakai tombol di bawah: di ponsel, tombol itu membuka aplikasi kamera; di komputer, pilih foto yang sudah ada.</p>
                    <p class="text-slate-600">Ulangi untuk tiap halaman. Tepi kertas dipotong otomatis; urutan dan sudut bisa diatur setelahnya.</p>
                </div>

                <p class="px-4 pt-3 text-xs text-slate-600" x-show="langsung && siap && ! atur">Letakkan surat di permukaan yang lebih gelap dari kertas, dengan cahaya merata. Garis hijau menandai bagian yang akan dipotong.</p>

                <ol x-show="halaman.length && ! atur" x-cloak class="flex gap-3 overflow-x-auto px-4 py-3" aria-label="Halaman yang sudah diambil">
                    <template x-for="(h, i) in halaman" :key="h.url">
                        <li class="relative w-36 shrink-0">
                            <span class="pointer-events-none absolute left-1 top-1 z-10 rounded bg-slate-900/75 px-1.5 text-xs text-white" aria-hidden="true" x-text="i + 1"></span>
                            <button type="button" :data-halaman="i" @click="bukaAtur(i)"
                                    class="block rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700"
                                    :aria-label="`Halaman ${i + 1}: ${labelCara(h).toLowerCase()}. Atur sudut`">
                                <img :src="h.url" alt="" class="h-44 w-36 rounded border border-slate-200 bg-white object-contain">
                            </button>
                            <p class="mt-1 text-xs" :class="h.cara === 'utuh' ? 'text-amber-800' : 'text-slate-600'"
                               x-text="labelCara(h)"></p>
                            <div class="flex items-center justify-between">
                                <button type="button" @click="geser(i, -1)" :disabled="i === 0" class="inline-flex h-11 w-11 items-center justify-center rounded text-base text-slate-700 hover:bg-slate-100 disabled:opacity-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                                    <span class="sr-only" x-text="`Geser halaman ${i + 1} ke depan`"></span><span aria-hidden="true">‹</span>
                                </button>
                                <button type="button" @click="geser(i, 1)" :disabled="i === halaman.length - 1" class="inline-flex h-11 w-11 items-center justify-center rounded text-base text-slate-700 hover:bg-slate-100 disabled:opacity-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                                    <span class="sr-only" x-text="`Geser halaman ${i + 1} ke belakang`"></span><span aria-hidden="true">›</span>
                                </button>
                                <button type="button" @click="hapus(i)" class="inline-flex h-11 w-11 items-center justify-center rounded text-base text-red-700 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                                    <span class="sr-only" x-text="`Hapus halaman ${i + 1}`"></span><span aria-hidden="true">✕</span>
                                </button>
                            </div>
                        </li>
                    </template>
                </ol>
            </div>

            <p class="sr-only" aria-live="polite" x-text="pengumuman"></p>

            <div x-show="atur" x-cloak class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-3">
                <button type="button" @click="aturOtomatis()" :disabled="sibuk" class="{{ $tombolKedua }}">Deteksi otomatis</button>
                <button type="button" @click="aturUtuh()" :disabled="sibuk" class="{{ $tombolKedua }}">Seluruh foto</button>
                <span class="hidden grow sm:block"></span>
                <button type="button" @click="tutupAtur()" :disabled="sibuk" class="{{ $tombolKedua }}">Batal</button>
                <button type="button" @click="simpanAtur()" :disabled="sibuk || ! sudutSah" class="{{ $tombolUtama }}"
                        x-text="sibuk ? 'Memotong…' : 'Simpan potongan'"></button>
            </div>

            <div x-show="! atur" class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-3">
                <button type="button" x-ref="tombolAmbil" x-show="langsung && siap && ! galat" @click="ambil()" :disabled="sibuk" class="{{ $tombolUtama }} grow sm:grow-0">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                    <span x-text="sibuk ? 'Memotong…' : 'Ambil halaman'">Ambil halaman</span>
                </button>
                <label class="{{ $tombolKedua }} cursor-pointer focus-within:ring-2 focus-within:ring-indigo-700" :class="(! langsung || galat) && 'grow sm:grow-0'">
                    <input type="file" accept="image/*" capture="environment" multiple class="sr-only" @change="tambahFoto($event)">
                    <span x-text="langsung && ! galat ? 'Dari foto' : 'Foto dengan aplikasi kamera'"></span>
                </label>
                <span class="hidden grow sm:block"></span>
                @if ($batch)
                    <button type="button" @click="kirim(true)" :disabled="! halaman.length || sibuk" class="{{ $tombolUtama }}">
                        Surat berikutnya
                    </button>
                    <button type="button" @click="halaman.length ? kirim(false) : tutup()" :disabled="sibuk" class="{{ $tombolKedua }}"
                            x-text="halaman.length ? 'Simpan ke daftar dan tutup' : 'Tutup kamera'"></button>
                @else
                    <button type="button" @click="kirim(false)" :disabled="! halaman.length || sibuk" class="{{ $tombolUtama }}"
                            x-text="halaman.length ? `Pakai ${halaman.length} halaman` : 'Pakai halaman'"></button>
                @endif
            </div>
        </div>
    </dialog>
</div>
