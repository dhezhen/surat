const KOLOM = ['nomor_surat_asal', 'tanggal_surat', 'pengirim', 'perihal', 'kategori'];

document.addEventListener('alpine:init', () => {
    /**
     * Formulir surat masuk dengan pembacaan otomatis (OCR) dari berkas scan.
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

        async pilihBerkas(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (this.pratinjauBlob) URL.revokeObjectURL(this.pratinjauBlob);
            this.pratinjauBlob = URL.createObjectURL(file);
            this.pratinjau = this.pratinjauBlob;
            this.tipePratinjau = file.type === 'application/pdf' ? 'pdf' : 'gambar';
            this.berkas = file;
            await this.baca();
        },

        async baca() {
            if (!this.berkas) return;
            this.status = 'membaca';
            this.pesan = 'Menyiapkan';
            this.persen = 2;
            try {
                const { bacaBerkas } = await import('./ocr');
                const r = await bacaBerkas(this.berkas, this.basis, (pesan, p) => {
                    this.pesan = pesan;
                    this.persen = Math.max(this.persen, Math.round(p * 100));
                });
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
            if (!this.hasil) return;
            KOLOM.forEach((k) => {
                let nilai = this.hasil[k];
                if (!nilai) return;
                if (k === 'kategori' && !this.kategoriSah.includes(nilai)) return;
                if (!timpa && this.disentuh[k]) return;
                if (!timpa && k !== 'tanggal_surat' && k !== 'kategori' && this.f[k]) return;
                this.f[k] = nilai;
                this.dariOcr[k] = true;
                if (timpa) this.disentuh[k] = false;
            });
        },

        get jumlahTerdeteksi() {
            return this.hasil ? KOLOM.filter((k) => this.hasil[k]).length : 0;
        },
    }));
});
