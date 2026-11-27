import { cembung, SUDUT_PENUH } from './potong';
import {
    berkasDariHalaman,
    buangHalaman,
    deteksiKertas,
    deteksiUlang,
    halamanDariFoto,
    potongUlang,
    kameraLangsungTersedia,
    matikanKamera,
    namaPindaian,
    nyalakanKamera,
    pesanGalatKamera,
    potretVideo,
} from './kamera';

const JEDA_PANDU = 300;
const LANGKAH_PANAH = 0.005;

/** Kotak tampilan video di dalam elemennya (object-contain menyisakan pita hitam). */
function kotakVideo(video) {
    const kw = video.clientWidth, kt = video.clientHeight;
    const skala = Math.min(kw / video.videoWidth, kt / video.videoHeight);
    const w = video.videoWidth * skala, h = video.videoHeight * skala;
    return { x: (kw - w) / 2, y: (kt - h) / 2, w, h };
}

export const KOLOM_OCR =['nomor_surat_asal', 'tanggal_surat', 'pengirim', 'perihal', 'kategori'];

/**
 * Mengisi kolom dari hasil OCR tanpa menimpa yang sudah diketik pengguna.
 * timpa = true: ganti semua kolom yang terbaca.
 */
export function isiDariOcr(target, hasil, kategoriSah, timpa = false) {
    if (!hasil) return;
    KOLOM_OCR.forEach((k) => {
        const nilai = hasil[k];
        if (!nilai) return;
        if (k === 'kategori' && !kategoriSah.includes(nilai)) return;
        if (!timpa && target.disentuh[k]) return;
        if (!timpa && k !== 'tanggal_surat' && k !== 'kategori' && target.f[k]) return;
        target.f[k] = nilai;
        target.dariOcr[k] = true;
        if (timpa) target.disentuh[k] = false;
    });
}

document.addEventListener('alpine:init', () => {
    /**
     * Dialog pemotret halaman surat. Selesai memotret, mengirim event "pindai-selesai"
     * berisi { berkas: File, halaman: [{ blob, url, lebar, tinggi, asli, sudut, cara }] }; penerima memiliki halaman itu.
     * Tiap foto dipotong otomatis mengikuti tepi kertas; sudutnya bisa diatur ulang sebelum dikirim.
     * Mode batch menambah tombol "Surat berikutnya" yang mengirim event yang sama tanpa menutup kamera.
     */
    window.Alpine.data('kameraPindai', ({ batch = false } = {}) => ({
        batch,
        langsung: kameraLangsungTersedia(),
        stream: null,
        siap: false,
        menyalakan: false,
        galat: '',
        halaman: [],
        sibuk: false,
        kilat: false,
        pengumuman: '',
        jumlahDikirim: 0,
        // Penyunting sudut: { i, url, sudut, aktif } selama terbuka.
        atur: null,
        pandu: null,
        pesanAtur: '',
        _timerPandu: null,
        _seret: null,

        async buka() {
            this.galat = '';
            this.$refs.dialog.showModal();
            if (this.langsung) await this.nyalakan();
        },

        async nyalakan() {
            this.menyalakan = true;
            try {
                this.stream = await nyalakanKamera(this.$refs.video);
                this.siap = true;
                this.$nextTick(() => this.$refs.tombolAmbil?.focus());
                this.jalankanPandu();
            } catch (err) {
                console.error(err);
                this.galat = pesanGalatKamera(err);
                this.siap = false;
            } finally {
                this.menyalakan = false;
            }
        },

        async ambil() {
            if (!this.siap || this.sibuk) return;
            this.sibuk = true;
            try {
                const h = await potretVideo(this.$refs.video);
                this.halaman.push(h);
                this.kilat = true;
                setTimeout(() => (this.kilat = false), 150);
                this.umumkan(`Halaman ${this.halaman.length} diambil. ${this.keteranganCara(h)}`);
            } catch (err) {
                this.galat = err.message;
            } finally {
                this.sibuk = false;
            }
        },

        async tambahFoto(e) {
            const files = [...e.target.files];
            e.target.value = '';
            if (!files.length) return;
            this.sibuk = true;
            this.galat = '';
            for (const file of files) {
                try {
                    this.halaman.push(await halamanDariFoto(file));
                } catch (err) {
                    console.error(err);
                    this.galat = `Foto ${file.name} tidak dapat dibaca. Pakai format JPG atau PNG.`;
                }
            }
            this.sibuk = false;
            this.umumkan(`${this.halaman.length} halaman siap.`);
        },

        labelCara(h) {
            return { otomatis: 'Terpotong otomatis', utuh: 'Utuh, ketuk untuk atur', manual: 'Diatur manual' }[h.cara];
        },

        keteranganCara(h) {
            return {
                otomatis: 'Tepi kertas terpotong otomatis.',
                utuh: 'Tepi kertas tidak ditemukan, foto disimpan utuh. Ketuk gambarnya untuk mengatur sudut.',
                manual: 'Sudut diatur manual.',
            }[h.cara];
        },

        /** Garis bantu: tepi kertas yang terdeteksi digambar di atas video, beberapa kali per detik. */
        jalankanPandu() {
            clearTimeout(this._timerPandu);
            const putaran = () => {
                const video = this.$refs.video;
                if (!this.stream || !this.$refs.dialog.open) return;
                if (!this.atur && video.videoWidth) {
                    this.pandu = deteksiKertas(video, video.videoWidth, video.videoHeight);
                    this.gambarPandu();
                }
                this._timerPandu = setTimeout(putaran, JEDA_PANDU);
            };
            putaran();
        },

        gambarPandu() {
            const kanvas = this.$refs.pandu;
            const video = this.$refs.video;
            if (!kanvas || !video.videoWidth) return;
            const rasio = window.devicePixelRatio || 1;
            kanvas.width = video.clientWidth * rasio;
            kanvas.height = video.clientHeight * rasio;
            const ctx = kanvas.getContext('2d');
            ctx.clearRect(0, 0, kanvas.width, kanvas.height);
            if (!this.pandu) return;
            const k = kotakVideo(video);
            ctx.scale(rasio, rasio);
            ctx.beginPath();
            this.pandu.forEach((p, i) => ctx[i ? 'lineTo' : 'moveTo'](k.x + p.x * k.w, k.y + p.y * k.h));
            ctx.closePath();
            // Hijau di atas jendela bidik hitam: tanda kertas sudah terbaca dan siap difoto.
            ctx.fillStyle = 'rgba(34, 197, 94, 0.18)';
            ctx.fill();
            ctx.lineWidth = 3;
            ctx.strokeStyle = '#22c55e';
            ctx.stroke();
        },

        bukaAtur(i) {
            const h = this.halaman[i];
            this.atur = { i, url: URL.createObjectURL(h.asli.blob), sudut: h.sudut.map((p) => ({ ...p })), aktif: 0 };
            this.pesanAtur = '';
            this.umumkan(`Mengatur sudut halaman ${i + 1}. Seret titik sudut, atau pilih titik lalu pakai tombol panah.`);
            this.$nextTick(() => this.$refs.dialog.querySelector('[data-sudut="0"]')?.focus());
        },

        tutupAtur() {
            if (!this.atur) return;
            const i = this.atur.i;
            URL.revokeObjectURL(this.atur.url);
            this.atur = null;
            this.$nextTick(() => this.$refs.dialog.querySelector(`[data-halaman="${i}"]`)?.focus());
        },

        mulaiSeret(e, k) {
            e.preventDefault();
            e.currentTarget.setPointerCapture(e.pointerId);
            this.atur.aktif = k;
            this._seret = k;
        },

        seret(e) {
            if (this._seret === null || !this.atur) return;
            const kotak = this.$refs.bidangAtur.getBoundingClientRect();
            const jepit = (v) => Math.min(1, Math.max(0, v));
            this.atur.sudut[this._seret] = { x: jepit((e.clientX - kotak.left) / kotak.width), y: jepit((e.clientY - kotak.top) / kotak.height) };
        },

        lepasSeret() {
            this._seret = null;
        },

        panah(e, k) {
            const arah = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
            if (!arah) return;
            e.preventDefault();
            const langkah = e.shiftKey ? LANGKAH_PANAH * 4 : LANGKAH_PANAH;
            const p = this.atur.sudut[k];
            const jepit = (v) => Math.min(1, Math.max(0, v));
            this.atur.sudut[k] = { x: jepit(p.x + arah[0] * langkah), y: jepit(p.y + arah[1] * langkah) };
        },

        async aturOtomatis() {
            const sudut = await deteksiUlang(this.halaman[this.atur.i]);
            if (sudut) {
                this.atur.sudut = sudut;
                this.pesanAtur = '';
                this.umumkan('Tepi kertas ditemukan.');
            } else {
                this.pesanAtur = 'Tepi kertas tidak ditemukan. Seret keempat titik ke sudut kertas.';
            }
        },

        aturUtuh() {
            this.atur.sudut = SUDUT_PENUH.map((p) => ({ ...p }));
            this.pesanAtur = '';
        },

        get sudutSah() {
            return !!this.atur && cembung(this.atur.sudut);
        },

        async simpanAtur() {
            if (!this.sudutSah || this.sibuk) return;
            this.sibuk = true;
            try {
                const i = this.atur.i;
                const lama = this.halaman[i];
                const utuh = this.atur.sudut.every((p, k) => Math.abs(p.x - SUDUT_PENUH[k].x) < 1e-3 && Math.abs(p.y - SUDUT_PENUH[k].y) < 1e-3);
                this.halaman[i] = await potongUlang(lama, utuh ? null : this.atur.sudut);
                buangHalaman([lama]);
                this.tutupAtur();
                this.umumkan(`Halaman ${i + 1} dipotong ulang.`);
            } catch (err) {
                console.error(err);
                this.pesanAtur = 'Halaman gagal dipotong. Coba atur ulang sudutnya.';
            } finally {
                this.sibuk = false;
            }
        },

        hapus(i) {
            buangHalaman(this.halaman.splice(i, 1));
            this.umumkan(`Halaman dihapus. Sisa ${this.halaman.length} halaman.`);
        },

        geser(i, arah) {
            const j = i + arah;
            if (j < 0 || j >= this.halaman.length) return;
            [this.halaman[i], this.halaman[j]] = [this.halaman[j], this.halaman[i]];
            this.umumkan(`Halaman dipindah ke urutan ${j + 1}.`);
        },

        /** lanjut = true: mode batch, kamera tetap menyala untuk surat berikutnya. */
        async kirim(lanjut = false) {
            if (!this.halaman.length || this.sibuk) return;
            this.sibuk = true;
            try {
                const halaman = [...this.halaman];
                const berkas = await berkasDariHalaman(halaman, namaPindaian());
                this.halaman = [];
                this.jumlahDikirim++;
                this.$dispatch('pindai-selesai', { berkas, halaman });
                if (lanjut) {
                    this.umumkan(`Surat ke-${this.jumlahDikirim} masuk daftar. Siap memotret surat berikutnya.`);
                    this.$refs.tombolAmbil?.focus();
                } else {
                    this.tutup();
                }
            } catch (err) {
                console.error(err);
                this.galat = 'Halaman gagal digabung. Coba lagi.';
            } finally {
                this.sibuk = false;
            }
        },

        // Escape atau tombol tutup: tanya dulu bila masih ada halaman yang belum dipakai.
        batal(e) {
            // Escape di penyunting sudut hanya menutup penyunting.
            if (this.atur) {
                e?.preventDefault();
                this.tutupAtur();
                return false;
            }
            if (this.halaman.length && !confirm(`Buang ${this.halaman.length} halaman yang belum dipakai?`)) {
                e?.preventDefault();
                return false;
            }
            return true;
        },

        tutup() {
            this.$refs.dialog.close();
        },

        tutupDenganTanya() {
            if (this.batal()) this.tutup();
        },

        bersihkan() {
            clearTimeout(this._timerPandu);
            this.pandu = null;
            if (this.atur) URL.revokeObjectURL(this.atur.url);
            this.atur = null;
            matikanKamera(this.stream, this.$refs.video);
            this.stream = null;
            this.siap = false;
            buangHalaman(this.halaman);
            this.halaman = [];
        },

        umumkan(teks) {
            this.pengumuman = '';
            this.$nextTick(() => (this.pengumuman = teks));
        },
    }));

    /** Halaman pindai banyak surat: tiap surat dibaca OCR bergiliran, diperiksa, lalu disimpan satu per satu. */
    window.Alpine.data('pindaiBanyak', (awal) => ({
        kategoriSah: awal.kategori,
        basis: awal.basis,
        urlSimpan: awal.urlSimpan,
        bawaan: { tanggal_diterima: awal.tanggal, kategori: awal.kategori[0] },
        surat: [],
        urut: 0,
        ocrJalan: false,
        menyimpanSemua: false,

        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.belumTersimpan.length) e.preventDefault();
            });
        },

        get belumTersimpan() {
            return this.surat.filter((s) => s.simpan !== 'tersimpan');
        },

        get siapDisimpan() {
            return this.surat.filter((s) => this.bisaDisimpan(s));
        },

        get jumlahTersimpan() {
            return this.surat.filter((s) => s.simpan === 'tersimpan').length;
        },

        bisaDisimpan(s) {
            return ['selesai', 'gagal'].includes(s.baca) && ['belum', 'gagal'].includes(s.simpan);
        },

        terima({ berkas, halaman }) {
            this.surat.push({
                id: ++this.urut,
                berkas,
                halaman,
                f: {
                    nomor_surat_asal: '', tanggal_surat: '', pengirim: '', perihal: '', keterangan: '',
                    tanggal_diterima: this.bawaan.tanggal_diterima,
                    kategori: this.bawaan.kategori,
                },
                disentuh: {},
                dariOcr: {},
                baca: 'antre', // antre | membaca | selesai | gagal
                persen: 0,
                pesan: '',
                jumlahTerbaca: 0,
                simpan: 'belum', // belum | menyimpan | tersimpan | gagal
                galat: {},
                galatUmum: '',
                arsip: null,
            });
            this.jalankanOcr();
        },

        async jalankanOcr() {
            if (this.ocrJalan) return;
            this.ocrJalan = true;
            const { bacaGambar } = await import('./ocr');
            let s;
            while ((s = this.surat.find((x) => x.baca === 'antre'))) {
                s.baca = 'membaca';
                s.pesan = 'Menyiapkan';
                s.persen = 2;
                try {
                    const r = await bacaGambar(s.halaman.map((h) => h.blob), this.basis, (pesan, p) => {
                        s.pesan = pesan;
                        s.persen = Math.max(s.persen, Math.round(p * 100));
                    });
                    isiDariOcr(s, r.hasil, this.kategoriSah);
                    s.jumlahTerbaca = KOLOM_OCR.filter((k) => r.hasil[k]).length;
                    s.baca = 'selesai';
                } catch (err) {
                    console.error(err);
                    s.baca = 'gagal';
                    s.pesan = err?.message || 'Teks tidak dapat dibaca.';
                }
            }
            this.ocrJalan = false;
        },

        sentuh(s, kolom) {
            s.disentuh[kolom] = true;
            s.dariOcr[kolom] = false;
            delete s.galat[kolom];
        },

        async simpan(s) {
            if (!this.bisaDisimpan(s)) return;
            s.simpan = 'menyimpan';
            s.galat = {};
            s.galatUmum = '';
            const data = new FormData();
            Object.entries(s.f).forEach(([k, v]) => data.append(k, v ?? ''));
            data.append('file_scan', s.berkas);
            try {
                const { data: arsip } = await window.axios.post(this.urlSimpan, data, { headers: { Accept: 'application/json' } });
                s.arsip = arsip;
                s.simpan = 'tersimpan';
            } catch (err) {
                s.simpan = 'gagal';
                const status = err.response?.status;
                if (status === 422) {
                    Object.entries(err.response.data.errors || {}).forEach(([k, pesan]) => (s.galat[k] = pesan[0]));
                    s.galatUmum = 'Periksa kolom yang ditandai.';
                } else if (status === 419) {
                    s.galatUmum = 'Sesi sudah habis. Muat ulang halaman lalu masuk kembali; surat di daftar ini akan hilang.';
                } else if (status === 413) {
                    s.galatUmum = 'Berkas terlalu besar untuk server. Hapus surat ini dan pindai dengan halaman lebih sedikit.';
                } else {
                    s.galatUmum = 'Gagal menyimpan. Periksa koneksi lalu coba lagi.';
                }
            }
        },

        async simpanSemua() {
            this.menyimpanSemua = true;
            for (const s of this.siapDisimpan) await this.simpan(s);
            this.menyimpanSemua = false;
        },

        hapus(s) {
            if (s.simpan !== 'tersimpan' && !confirm(`Hapus surat ${this.surat.indexOf(s) + 1} dari daftar? Fotonya ikut terbuang.`)) return;
            buangHalaman(s.halaman);
            this.surat = this.surat.filter((x) => x !== s);
        },

        bacaUlang(s) {
            s.baca = 'antre';
            this.jalankanOcr();
        },
    }));
});
