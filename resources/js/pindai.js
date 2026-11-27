import {
    berkasDariHalaman,
    buangHalaman,
    halamanDariFoto,
    kameraLangsungTersedia,
    matikanKamera,
    namaPindaian,
    nyalakanKamera,
    pesanGalatKamera,
    potretVideo,
} from './kamera';

export const KOLOM_OCR = ['nomor_surat_asal', 'tanggal_surat', 'pengirim', 'perihal', 'kategori'];

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
     * berisi { berkas: File, halaman: [{ blob, url, lebar, tinggi }] }; penerima memiliki halaman itu.
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
                this.halaman.push(await potretVideo(this.$refs.video));
                this.kilat = true;
                setTimeout(() => (this.kilat = false), 150);
                this.umumkan(`Halaman ${this.halaman.length} diambil.`);
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
