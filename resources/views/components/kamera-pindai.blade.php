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
                <div x-show="langsung && ! galat" class="relative bg-black">
                    <video x-ref="video" playsinline muted class="mx-auto h-[50vh] w-full object-contain sm:h-[55vh]"></video>
                    {{-- Kilat putih tanda foto diambil; warna tetap, bukan warna tema. --}}
                    <div x-show="kilat" class="pointer-events-none absolute inset-0" style="background: rgb(255 255 255 / 0.7)" aria-hidden="true"></div>
                    <p x-show="menyalakan" class="absolute inset-0 flex items-center justify-center text-sm text-white">Menyalakan kamera…</p>
                </div>

                <div x-show="! langsung || galat" x-cloak class="space-y-2 px-4 py-4 text-sm">
                    <p x-show="galat" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-800" x-text="galat"></p>
                    <p x-show="! langsung" class="text-slate-700">Kamera tidak bisa dibuka langsung di halaman ini karena aplikasi belum diakses lewat HTTPS. Pakai tombol di bawah: di ponsel, tombol itu membuka aplikasi kamera; di komputer, pilih foto yang sudah ada.</p>
                    <p class="text-slate-600">Ulangi untuk tiap halaman. Urutan halaman bisa diatur setelahnya.</p>
                </div>

                <p class="px-4 pt-3 text-xs text-slate-600" x-show="langsung && siap">Letakkan surat di permukaan gelap dengan cahaya merata, lalu isi bingkai dengan satu halaman.</p>

                <ol x-show="halaman.length" x-cloak class="flex gap-3 overflow-x-auto px-4 py-3" aria-label="Halaman yang sudah diambil">
                    <template x-for="(h, i) in halaman" :key="h.url">
                        <li class="relative w-36 shrink-0">
                            <span class="absolute left-1 top-1 rounded bg-slate-900/75 px-1.5 text-xs text-white" aria-hidden="true" x-text="i + 1"></span>
                            <img :src="h.url" :alt="`Halaman ${i + 1}`" class="h-44 w-36 rounded border border-slate-200 bg-white object-cover">
                            <div class="mt-1 flex items-center justify-between">
                                <span class="sr-only" x-text="`Halaman ${i + 1}`"></span>
                                <span class="flex">
                                    <button type="button" @click="geser(i, -1)" :disabled="i === 0" class="inline-flex h-11 w-11 items-center justify-center rounded text-base text-slate-700 hover:bg-slate-100 disabled:opacity-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                                        <span class="sr-only" x-text="`Geser halaman ${i + 1} ke depan`"></span><span aria-hidden="true">‹</span>
                                    </button>
                                    <button type="button" @click="geser(i, 1)" :disabled="i === halaman.length - 1" class="inline-flex h-11 w-11 items-center justify-center rounded text-base text-slate-700 hover:bg-slate-100 disabled:opacity-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                                        <span class="sr-only" x-text="`Geser halaman ${i + 1} ke belakang`"></span><span aria-hidden="true">›</span>
                                    </button>
                                    <button type="button" @click="hapus(i)" class="inline-flex h-11 w-11 items-center justify-center rounded text-base text-red-700 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700">
                                        <span class="sr-only" x-text="`Hapus halaman ${i + 1}`"></span><span aria-hidden="true">✕</span>
                                    </button>
                                </span>
                            </div>
                        </li>
                    </template>
                </ol>
            </div>

            <p class="sr-only" aria-live="polite" x-text="pengumuman"></p>

            <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-4 py-3">
                <button type="button" x-ref="tombolAmbil" x-show="langsung && siap && ! galat" @click="ambil()" :disabled="sibuk" class="{{ $tombolUtama }} grow sm:grow-0">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                    Ambil halaman
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
                @endif
                @if ($batch)
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
