const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

const BLOK_KEPALA =
    '<table style="width: 100%"><tbody>' +
    '<tr><td style="width: 2.4cm">Nomor</td><td style="width: 0.4cm">:</td><td>{{nomor_surat}}</td></tr>' +
    '<tr><td>Lampiran</td><td>:</td><td>{{lampiran}}</td></tr>' +
    '<tr><td>Perihal</td><td>:</td><td>{{perihal}}</td></tr>' +
    '</tbody></table>' +
    '<p><strong>Kepada Yth.<br>{{tujuan_surat}}<br>Di tempat.</strong></p>';

const TABEL_ISIAN =
    '<table style="margin-left: 1.2cm"><tbody>' +
    '<tr><td style="width: 3cm">Label</td><td>: Isi</td></tr>' +
    '<tr><td>Label</td><td>: Isi</td></tr>' +
    '</tbody></table><p><br></p>';

document.addEventListener('alpine:init', () => {
    /**
     * Menskalakan kertas A4 (lebar tetap 21 cm) agar selalu muat utuh di kolomnya.
     * Tinggi wadah mengikuti tinggi kertas setelah diskalakan, karena transform tidak mengubah tata letak.
     */
    window.Alpine.data('kertasPas', () => ({
        skala: 1,
        tinggi: 0,

        init() {
            const ukur = () => {
                const kertas = this.$refs.kertas;
                const lebarKertas = kertas.offsetWidth;
                if (!lebarKertas) return;
                this.skala = Math.min(1, this.$el.clientWidth / lebarKertas);
                this.tinggi = kertas.offsetHeight * this.skala;
            };
            const pengamat = new ResizeObserver(ukur);
            pengamat.observe(this.$el);
            pengamat.observe(this.$refs.kertas);
            ukur();
        },
    }));

    /**
     * Editor visual ringan berbasis contenteditable. Dipilih ketimbang pustaka WYSIWYG agar markup yang
     * sudah ada (tabel, gaya, kelas menjorok) tidak dibuang saat template dibuka kembali.
     * Dipakai dengan x-model pada elemen akarnya (lewat x-modelable).
     */
    window.Alpine.data('editorVisual', (opsi = {}) => ({
        html: '',
        template: !!opsi.template,
        rentang: null,

        // Penyusun kalimat AI: hasilnya ditampilkan dulu dan baru masuk ke editor setelah staff memilihnya.
        panelAi: false,
        pokok: '',
        hasilAi: '',
        aiMemuat: false,
        aiGalat: '',

        init() {
            this.$refs.area.innerHTML = this.html || '';
            this.$watch('html', (v) => {
                if (v !== this.$refs.area.innerHTML) this.$refs.area.innerHTML = v || '';
            });
        },

        sinkron() {
            this.html = this.$refs.area.innerHTML;
        },

        fokus() {
            this.$refs.area.focus();
            try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* peramban lama */ }
        },

        simpanRentang() {
            const sel = window.getSelection();
            if (sel.rangeCount && this.$refs.area.contains(sel.anchorNode)) this.rentang = sel.getRangeAt(0).cloneRange();
        },

        pulihkanRentang() {
            this.fokus();
            if (this.rentang) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(this.rentang);
            }
        },

        perintah(nama, arg = null) {
            this.pulihkanRentang();
            document.execCommand(nama, false, arg);
            this.simpanRentang();
            this.sinkron();
        },

        sisipHtml(markup) {
            this.perintah('insertHTML', markup);
        },

        sisipTeks(teks) {
            this.perintah('insertText', teks);
        },

        sisipKepala() {
            this.sisipHtml(BLOK_KEPALA);
        },

        // Teks sesudah penanda ini dicetak sebagai halaman lampiran (tanpa kop) setelah tanda tangan.
        sisipHalamanBaru() {
            this.sisipTeks('{{halaman_baru}}');
        },

        sisipTabel() {
            this.sisipHtml(TABEL_ISIAN);
        },

        // Menjorok baris pertama paragraf, sesuai kebiasaan surat dinas.
        jorok() {
            this.pulihkanRentang();
            const sel = window.getSelection();
            let el = sel.anchorNode;
            while (el && el !== this.$refs.area && !(el.nodeType === 1 && /^(P|DIV|LI)$/.test(el.tagName))) el = el.parentNode;
            if (el && el !== this.$refs.area) el.classList.toggle('indent');
            this.sinkron();
        },

        async susunAi() {
            if (this.aiMemuat) return;
            this.aiGalat = '';
            this.hasilAi = '';
            if (this.pokok.trim().length < 10) {
                this.aiGalat = 'Tuliskan pokok isi surat terlebih dahulu (minimal satu kalimat).';
                return;
            }

            let konteks = {};
            try { konteks = JSON.parse(this.$el.dataset.konteks || '{}'); } catch (e) { /* tanpa konteks */ }

            this.aiMemuat = true;
            try {
                const res = await fetch(opsi.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ pokok: this.pokok, perihal: konteks.perihal || '', tujuan: konteks.tujuan || '', jenis: konteks.jenis || '' }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const pesanValidasi = data.errors ? Object.values(data.errors).flat()[0] : null;
                    this.aiGalat = res.status === 429
                        ? 'Terlalu sering memakai AI. Tunggu satu menit lalu coba lagi.'
                        : (pesanValidasi || data.message || 'AI gagal menyusun kalimat.');
                    return;
                }
                this.hasilAi = data.html;
            } catch (e) {
                this.aiGalat = 'Tidak dapat menghubungi server. Periksa koneksi Anda.';
            } finally {
                this.aiMemuat = false;
            }
        },

        pakaiHasilAi(ganti) {
            if (!this.hasilAi) return;
            if (ganti) {
                this.html = this.hasilAi;
            } else {
                this.sisipHtml(this.hasilAi);
            }
            this.hasilAi = '';
        },

        // Tempel sebagai teks polos agar format dari Word/web tidak ikut masuk.
        tempel(e) {
            const teks = (e.clipboardData || window.clipboardData).getData('text/plain');
            const paragraf = teks.split(/\r?\n{2,}/).map((p) => p.trim()).filter(Boolean);
            if (paragraf.length > 1) this.sisipHtml(paragraf.map((p) => '<p>' + esc(p).replace(/\r?\n/g, '<br>') + '</p>').join(''));
            else this.perintah('insertText', teks);
        },
    }));

    /**
     * Pembuat template: daftar isian formulir (tanpa JSON) dan isi template.
     * Nama teknis tiap isian dibuat otomatis dari labelnya dan dipakai sebagai {{placeholder}}.
     */
    window.Alpine.data('templateBuilder', (fieldAwal, templateAwal) => ({
        fields: fieldAwal.map((f) => ({
            name: f.name,
            label: f.label || f.name,
            type: f.type || 'text',
            required: !!f.required,
            default: f.default || '',
            options: (f.options || []).join(', '),
            kolom: (f.kolom || []).join(', '),
            baru: false,
        })),
        templateHtml: templateAwal,
        mode: 'visual',
        bawaan: ['nomor_surat', 'tanggal_surat', 'tujuan_surat', 'perihal'],
        // Penanda khusus bagi isi surat dan halaman lampiran; bukan isian formulir.
        khusus: ['halaman_baru'],

        tambah() {
            this.fields.push({ name: '', label: '', type: 'text', required: false, default: '', options: '', kolom: 'Nama, Keterangan', baru: true });
        },

        hapus(i) {
            this.fields.splice(i, 1);
        },

        geser(i, arah) {
            const j = i + arah;
            if (j < 0 || j >= this.fields.length) return;
            [this.fields[i], this.fields[j]] = [this.fields[j], this.fields[i]];
        },

        slug(label) {
            let s = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            if (!s || !/^[a-z]/.test(s)) s = 'isian_' + s;
            return s.replace(/_+$/, '');
        },

        namaUnik(dasar, kecuali) {
            const dipakai = new Set([...this.bawaan, ...this.khusus, ...this.fields.filter((f) => f !== kecuali).map((f) => f.name)]);
            let nama = dasar, n = 2;
            while (dipakai.has(nama)) nama = dasar + '_' + n++;
            return nama;
        },

        // Nama teknis dibuat saat label selesai diketik; yang sudah dipakai template tidak diubah lagi.
        beresLabel(f) {
            if (f.baru && f.label.trim()) f.name = this.namaUnik(this.slug(f.label), f);
        },

        sisipField(nama) {
            window.Alpine.$data(document.getElementById('editor-template')).sisipTeks('{{' + nama + '}}');
        },

        get skema() {
            return this.fields
                .filter((f) => f.label.trim() && f.name)
                .map((f) => {
                    const o = { name: f.name, label: f.label.trim(), type: f.type, required: f.required };
                    if (f.default.trim()) o.default = f.default.trim();
                    if (f.type === 'select') o.options = f.options.split(',').map((x) => x.trim()).filter(Boolean);
                    if (f.type === 'tabel') o.kolom = f.kolom.split(',').map((x) => x.trim()).filter(Boolean).slice(0, 8);
                    return o;
                });
        },
    }));
});
