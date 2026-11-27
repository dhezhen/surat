import { buangHalaman } from './kamera';
import { KOLOM_OCR, isiDariOcr } from './pindai';

document.addEventListener('alpine:init', () => {
    /**
     * Formulir surat masuk dengan pembacaan otomatis (OCR) dari berkas scan atau foto kamera.
     * Hasil OCR hanya saran: kolom yang diisi otomatis ditandai agar diperiksa, dan kolom yang
     * sudah diketik pengguna tidak ditimpa.
     */
    window.Alpine.data('suratMasukForm', (awal) => ({
        f: { ...awal.nilai },
        kategoriSah: awal.kategori,
        basis: awal.basis,
        disentuh: {},
        dariOcr: {},
        pratinjau: awal.pratinjau,
        tipePratinjau: awal.tipePratinjau,
        halamanPindai: [],
        status: 'diam', // diam | membaca | selesai | gagal
        pesan: '',
        persen: 0,
        teks: '',
        metode: '',
        hasil: null,
        berkas: null,

        sentuh(kolom) {
            this.disentuh[kolom] = true;
            this.dariOcr[kolom] = false;
        },

        lepasPratinjau() {
            if (this.pratinjauBlob) URL.revokeObjectURL(this.pratinjauBlob);
            this.pratinjauBlob = null;
            buangHalaman(this.halamanPindai);
            this.halamanPindai = [];
        },

        async pilihBerkas(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.lepasPratinjau();
            this.pratinjauBlob = URL.createObjectURL(file);
            this.pratinjau = this.pratinjauBlob;
            this.tipePratinjau = file.type === 'application/pdf' ? 'pdf' : 'gambar';
            this.berkas = file;
            await this.baca();
        },

        /** Hasil kamera dimasukkan ke kolom berkas agar terkirim bersama formulir seperti berkas biasa. */
        async terimaPindaian({ berkas, halaman }) {
            this.lepasPratinjau();
            const dt = new DataTransfer();
            dt.items.add(berkas);
            this.$refs.berkas.files = dt.files;
            this.halamanPindai = halaman;
            this.pratinjau = halaman[0].url;
            this.tipePratinjau = 'halaman';
            this.berkas = berkas;
            await this.baca();
        },

        async baca() {
            if (!this.berkas) return;
            this.status = 'membaca';
            this.pesan = 'Menyiapkan';
            this.persen = 2;
            try {
                const ocr = await import('./ocr');
                const lapor = (pesan, p) => {
                    this.pesan = pesan;
                    this.persen = Math.max(this.persen, Math.round(p * 100));
                };
                const r = this.halamanPindai.length
                    ? await ocr.bacaGambar(this.halamanPindai.map((h) => h.blob), this.basis, lapor)
                    : await ocr.bacaBerkas(this.berkas, this.basis, lapor);
                this.teks = r.teks;
                this.metode = r.metode;
                this.hasil = r.hasil;
                this.terapkan(false);
                this.status = 'selesai';
                this.persen = 100;
            } catch (err) {
                console.error(err);
                this.status = 'gagal';
                this.pesan = err && err.message ? err.message : 'Berkas tidak dapat dibaca.';
            }
        },

        // timpa = true: ganti semua kolom, termasuk yang sudah diketik pengguna.
        terapkan(timpa) {
            isiDariOcr(this, this.hasil, this.kategoriSah, timpa);
        },

        get jumlahTerdeteksi() {
            return this.hasil ? KOLOM_OCR.filter((k) => this.hasil[k]).length : 0;
        },
    }));
});
