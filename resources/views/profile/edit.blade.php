<x-app-layout>
    <x-slot name="title">Profil</x-slot>
    <x-slot name="header"><h1 class="text-xl font-semibold text-slate-900">Profil Akun</h1></x-slot>

    <div class="max-w-2xl space-y-6">
        <section class="bg-white border border-slate-200 rounded-lg p-6">
            <h2 class="font-semibold text-slate-900">Data Pengguna</h2>
            <p class="mt-1 text-sm text-slate-600">Nama lengkap beserta gelar dan jabatan dipakai pada blok tanda tangan surat.</p>

            <form method="post" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <x-input-label for="name" value="Nama lengkap beserta gelar" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $user->name)" required />
                </div>
                <div>
                    <x-input-label for="jabatan" value="Jabatan" />
                    <x-text-input id="jabatan" name="jabatan" class="mt-1 block w-full" :value="old('jabatan', $user->jabatan)" required />
                </div>
                <div>
                    <x-input-label for="nidn" :value="\App\Support\Identitas::ambil('label_id_penandatangan').' (opsional)'" />
                    <x-text-input id="nidn" name="nidn" class="mt-1 block w-full" :value="old('nidn', $user->nidn)" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="notif_email" value="1" @checked(old('notif_email', $user->notif_email)) aria-describedby="bantuan-notif-email" class="rounded border-slate-300 text-indigo-800">
                        Kirim pemberitahuan surat ke email ini
                    </label>
                    <p id="bantuan-notif-email" class="mt-1 text-xs text-slate-600">Saat surat diajukan, disetujui, atau diminta revisi. Notifikasi di aplikasi tetap muncul walau email dimatikan.</p>
                </div>
                <x-primary-button>Simpan</x-primary-button>
            </form>
        </section>

        @if ($user->isKaprodi())
            <section class="bg-white border border-slate-200 rounded-lg p-6" x-data="padTandaTangan()">
                <h2 class="font-semibold text-slate-900">Gambar Tanda Tangan Digital</h2>
                <p class="mt-1 text-sm text-slate-600">Dipakai pada surat dengan metode TTD digital, tampil di samping QR Code. Latar putih otomatis dibuat transparan.</p>

                @if ($user->signature_path)
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        @if ($gambar = $user->tandaTanganDataUri())
                            <img src="{{ $gambar }}" alt="Tanda tangan tersimpan" class="tema-terang h-20 max-w-xs rounded border border-slate-200 bg-white p-2">
                        @else
                            <p class="text-sm text-red-700">Berkas tanda tangan tidak ditemukan di server. Unggah ulang di bawah.</p>
                        @endif
                        <form method="post" action="{{ route('profile.tanda-tangan.hapus') }}" onsubmit="return confirm('Hapus tanda tangan tersimpan?')">
                            @csrf @method('delete')
                            <button class="text-sm text-red-700 underline">Hapus tanda tangan</button>
                        </form>
                    </div>
                    <p class="mt-4 text-sm font-medium text-slate-700">Ganti dengan yang baru:</p>
                @endif

                <div class="mt-4 flex gap-1 border-b border-slate-200 text-sm" role="tablist">
                    <button type="button" role="tab" :aria-selected="(mode === 'gambar').toString()" @click="mode = 'gambar'" class="px-4 py-2 -mb-px border-b-2" :class="mode === 'gambar' ? 'border-indigo-800 font-semibold text-indigo-900' : 'border-transparent text-slate-600'">Gambar langsung</button>
                    <button type="button" role="tab" :aria-selected="(mode === 'unggah').toString()" @click="mode = 'unggah'" class="px-4 py-2 -mb-px border-b-2" :class="mode === 'unggah' ? 'border-indigo-800 font-semibold text-indigo-900' : 'border-transparent text-slate-600'">Unggah foto/scan</button>
                </div>

                <form x-show="mode === 'gambar'" method="post" action="{{ route('profile.tanda-tangan') }}" @submit="kirim($event)" class="mt-4">
                    @csrf
                    <input type="hidden" name="tanda_tangan_gambar" x-ref="data">
                    <canvas x-ref="kanvas" class="tema-terang w-full rounded-md border border-dashed border-slate-400 bg-white" style="height:180px; touch-action:none" aria-label="Area menggambar tanda tangan"></canvas>
                    <p x-show="kosong" x-cloak class="mt-2 text-sm text-red-700">Gambar tanda tangan di kolom terlebih dahulu.</p>
                    <div class="mt-3 flex items-center gap-3">
                        <x-primary-button>Simpan tanda tangan</x-primary-button>
                        <button type="button" @click="bersihkan()" class="text-sm text-slate-600 underline">Hapus coretan</button>
                    </div>
                </form>

                <form x-show="mode === 'unggah'" x-cloak method="post" action="{{ route('profile.tanda-tangan') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <input type="file" name="tanda_tangan" accept="image/png,image/jpeg" required class="block text-sm">
                    <p class="text-xs text-slate-600">PNG atau JPG, maks. 4 MB. Foto di atas kertas putih dengan tinta gelap memberi hasil terbaik.</p>
                    <x-primary-button>Unggah</x-primary-button>
                </form>
            </section>

            @push('scripts')
            <script>
                function padTandaTangan() {
                    return {
                        mode: 'gambar',
                        kosong: false,
                        ctx: null,
                        menggambar: false,
                        ada: false,
                        init() {
                            const k = this.$refs.kanvas;
                            const skala = window.devicePixelRatio || 1;
                            const sisiKanan = k.getBoundingClientRect();
                            k.width = sisiKanan.width * skala;
                            k.height = sisiKanan.height * skala;
                            this.ctx = k.getContext('2d');
                            this.ctx.scale(skala, skala);
                            this.ctx.lineWidth = 2.4;
                            this.ctx.lineCap = 'round';
                            this.ctx.lineJoin = 'round';
                            this.ctx.strokeStyle = '#111827';

                            const titik = (e) => {
                                const r = k.getBoundingClientRect();
                                return [e.clientX - r.left, e.clientY - r.top];
                            };
                            k.addEventListener('pointerdown', (e) => {
                                this.menggambar = true;
                                this.kosong = false;
                                k.setPointerCapture(e.pointerId);
                                const [x, y] = titik(e);
                                this.ctx.beginPath();
                                this.ctx.moveTo(x, y);
                                this.ctx.lineTo(x + 0.01, y + 0.01);
                                this.ctx.stroke();
                                this.ada = true;
                            });
                            k.addEventListener('pointermove', (e) => {
                                if (!this.menggambar) return;
                                const [x, y] = titik(e);
                                this.ctx.lineTo(x, y);
                                this.ctx.stroke();
                            });
                            const selesai = () => { this.menggambar = false; };
                            k.addEventListener('pointerup', selesai);
                            k.addEventListener('pointercancel', selesai);
                        },
                        bersihkan() {
                            const k = this.$refs.kanvas;
                            this.ctx.clearRect(0, 0, k.width, k.height);
                            this.ada = false;
                        },
                        kirim(e) {
                            if (!this.ada) {
                                e.preventDefault();
                                this.kosong = true;
                                return;
                            }
                            this.$refs.data.value = this.$refs.kanvas.toDataURL('image/png');
                        },
                    };
                }
            </script>
            @endpush
        @endif

        <section class="bg-white border border-slate-200 rounded-lg p-6">
            <div class="max-w-xl">@include('profile.partials.update-password-form')</div>
        </section>
    </div>
</x-app-layout>
