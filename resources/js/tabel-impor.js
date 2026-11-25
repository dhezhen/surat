// Batas ini harus sama dengan aturan validasi tabel di SuratKeluarController.
export const MAKS_KOLOM = 8;
export const MAKS_BARIS = 300;
const MAKS_SEL = 255;
const MAKS_JUDUL = 60;
const MAKS_BERKAS = 5 * 1024 * 1024;

/** Mengurai teks berpemisah. Sel berkutip boleh memuat pemisah dan baris baru, seperti salinan dari Excel. */
export function uraiTeks(teks, pemisah) {
    const baris = [[]];
    let sel = '';
    let berkutip = false;
    for (let i = 0; i < teks.length; i++) {
        const c = teks[i];
        if (berkutip) {
            if (c !== '"') sel += c;
            else if (teks[i + 1] === '"') { sel += '"'; i++; }
            else berkutip = false;
        } else if (c === '"' && sel === '') {
            berkutip = true;
        } else if (c === pemisah) {
            baris.at(-1).push(sel);
            sel = '';
        } else if (c === '\n' || c === '\r') {
            if (c === '\r' && teks[i + 1] === '\n') i++;
            baris.at(-1).push(sel);
            sel = '';
            baris.push([]);
        } else {
            sel += c;
        }
    }
    baris.at(-1).push(sel);
    return baris;
}

// Excel berbahasa Indonesia menulis CSV dengan titik koma, bukan koma.
export function tebakPemisah(teks) {
    const awal = teks.split(/\r?\n/, 1)[0].replace(/"[^"]*"/g, '');
    return ['\t', ';', ','].reduce((a, b) => (awal.split(b).length > awal.split(a).length ? b : a));
}

export function teksSel(nilai) {
    if (nilai === null || nilai === undefined) return '';
    if (nilai instanceof Date) {
        // Sel yang hanya berisi jam tersimpan sebagai tanggal di sekitar tahun 1899.
        if (nilai.getUTCFullYear() < 1901) return nilai.toISOString().slice(11, 16).replace(':', '.');
        return nilai.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    }
    if (typeof nilai === 'object' && 'angka' in nilai) {
        // Bilangan bulat dipakai apa adanya agar NIM atau NIK panjang tidak dibulatkan.
        if (/^-?\d+$/.test(nilai.angka)) return nilai.angka;
        return String(Number(Number(nilai.angka).toPrecision(15))).replace('.', ',');
    }
    return String(nilai).replace(/\s*[\r\n]+\s*/g, ' ').trim();
}

/** Membuang baris dan kolom yang seluruhnya kosong, lalu memisahkan baris judul bila diminta. */
export function siapkan(mentah, pakaiJudul) {
    let baris = mentah.map((b) => b.map(teksSel)).filter((b) => b.some((c) => c !== ''));
    const lebar = baris.reduce((n, b) => Math.max(n, b.length), 0);
    const terisi = [...Array(lebar).keys()].filter((j) => baris.some((b) => (b[j] ?? '') !== ''));
    baris = baris.map((b) => terisi.map((j) => b[j] ?? ''));

    // Satu baris saja pasti data; menjadikannya judul akan menyisakan tabel kosong.
    const adaJudul = pakaiJudul && baris.length > 1;
    const judul = adaJudul ? baris.shift() : terisi.map(() => '');

    return { judul, baris, adaJudul };
}

/** Kolom nomor urut dikenali dari judul "No" dan isi 1, 2, 3; tabel surat sudah menomori sendiri. */
export function kolomNomorUrut(data) {
    return data.judul.findIndex((j, i) => /^no\.?$/i.test(j) && data.baris.every((b, n) => ['', String(n + 1), `${n + 1}.`].includes(b[i])));
}

export function kolomBawaan(data) {
    const nomor = kolomNomorUrut(data);
    return [...data.judul.keys()].filter((i) => i !== nomor).slice(0, MAKS_KOLOM);
}

/**
 * Menyusun isi tabel baru dari data impor. Tabel lama tidak diubah di sini.
 * Saat menambahkan di bawah data lama, kolom dicocokkan menurut urutan.
 */
export function gabung(tabel, data, { pilihan, ganti }) {
    const peringatan = [];
    const lama = tabel.baris.filter((b) => b.some((c) => String(c).trim() !== ''));
    const mulaiBaru = ganti || lama.length === 0;
    const kolomDipakai = pilihan.slice(0, MAKS_KOLOM);
    const lebar = mulaiBaru ? kolomDipakai.length : Math.max(tabel.kolom.length, kolomDipakai.length);

    let judulDipotong = 0;
    const kolom = [...Array(lebar).keys()].map((j) => {
        if (!mulaiBaru && j < tabel.kolom.length) return tabel.kolom[j];
        const judul = data.adaJudul ? (data.judul[kolomDipakai[j]] ?? '') : (tabel.kolom[j] ?? '');
        if (judul.length > MAKS_JUDUL) judulDipotong++;
        return judul.slice(0, MAKS_JUDUL);
    });

    const awal = mulaiBaru ? [] : lama.map((b) => kolom.map((_, j) => b[j] ?? ''));
    const muat = Math.max(0, MAKS_BARIS - awal.length);
    let selDipotong = 0;
    const tambahan = data.baris.slice(0, muat).map((b) => kolom.map((_, j) => {
        const isi = j < kolomDipakai.length ? b[kolomDipakai[j]] : '';
        if (isi.length > MAKS_SEL) selDipotong++;
        return isi.slice(0, MAKS_SEL);
    }));

    if (data.baris.length > muat) {
        peringatan.push(`${data.baris.length - muat} baris terakhir tidak dimasukkan karena tabel dibatasi ${MAKS_BARIS} baris.`);
    }
    if (selDipotong) peringatan.push(`${selDipotong} sel lebih dari ${MAKS_SEL} karakter dan dipotong.`);
    if (judulDipotong) peringatan.push(`${judulDipotong} judul kolom lebih dari ${MAKS_JUDUL} karakter dan dipotong.`);
    if (!mulaiBaru && data.adaJudul) peringatan.push('Judul kolom lama dipertahankan. Periksa apakah urutan kolomnya sesuai.');

    return { kolom, baris: [...awal, ...tambahan], ditambahkan: tambahan.length, peringatan };
}

async function bacaLembar(berkas) {
    if (/\.xlsx$/i.test(berkas.name)) {
        const { default: baca } = await import('read-excel-file/browser');
        // Fungsi ini disalin ke web worker, jadi tidak boleh memakai variabel dari luar.
        return baca(berkas, { parseNumber: (angka) => ({ angka }) });
    }

    const isi = await berkas.arrayBuffer();
    let teks;
    try {
        teks = new TextDecoder('utf-8', { fatal: true }).decode(isi);
    } catch {
        teks = new TextDecoder('windows-1252').decode(isi);
    }
    return [{ sheet: berkas.name, data: uraiTeks(teks, tebakPemisah(teks)) }];
}

export function imporTabel() {
    // Isi berkas disimpan di luar data reaktif agar Alpine tidak membungkus ribuan sel.
    let lembar = [];
    let data = null;

    return {
        pakaiJudul: true,
        membaca: false,
        galat: '',
        hasil: '',
        peringatan: [],
        cadangan: null,
        namaSheet: [],
        sheet: 0,
        kolomBerkas: [],
        pilihan: [],
        jumlahBaris: 0,
        ganti: 'tambah',
        maksKolom: MAKS_KOLOM,

        async bacaBerkas(input) {
            const berkas = input.files[0];
            input.value = '';
            if (!berkas) return;
            this.batal();
            if (/\.xls$/i.test(berkas.name)) {
                this.galat = 'Format .xls lama belum didukung. Di Excel, pilih Simpan Sebagai dengan jenis .xlsx, lalu unggah lagi.';
                return;
            }
            if (!/\.(xlsx|csv|tsv|txt)$/i.test(berkas.name)) {
                this.galat = 'Pilih berkas .xlsx atau .csv.';
                return;
            }
            if (berkas.size > MAKS_BERKAS) {
                this.galat = 'Berkas lebih dari 5 MB. Salin hanya sheet yang dibutuhkan ke berkas baru.';
                return;
            }

            this.membaca = true;
            try {
                lembar = await bacaLembar(berkas);
                this.namaSheet = lembar.map((l) => l.sheet);
                this.sheet = 0;
                this.susunUlang();
            } catch (e) {
                console.error(e);
                this.batal();
                this.galat = 'Berkas tidak dapat dibaca. Pastikan tidak diberi kata sandi, atau simpan ulang sebagai .xlsx.';
            } finally {
                this.membaca = false;
            }
        },

        susunUlang() {
            if (!lembar.length) return;
            data = siapkan(lembar[this.sheet].data, this.pakaiJudul);
            this.jumlahBaris = data.baris.length;
            this.kolomBerkas = data.judul.map((judul, i) => ({
                i,
                judul: judul || `Kolom ${i + 1}`,
                contoh: data.baris.find((b) => b[i] !== '')?.[i] ?? '',
            }));
            this.pilihan = kolomBawaan(data);
        },

        terapkan(tabel) {
            const urut = [...this.pilihan].sort((a, b) => a - b);
            this.pasang(tabel, gabung(tabel, data, { pilihan: urut, ganti: this.ganti === 'ganti' }));
            this.batal(true);
        },

        tempel(tabel, teks) {
            this.batal();
            const hasilUrai = siapkan(uraiTeks(teks, '\t'), this.pakaiJudul);
            if (!hasilUrai.baris.length) {
                this.galat = 'Teks yang ditempel tidak berisi data.';
                return;
            }
            const pilihan = kolomBawaan(hasilUrai);
            const susunan = gabung(tabel, hasilUrai, { pilihan, ganti: false });
            const nomor = kolomNomorUrut(hasilUrai);
            const dilewati = hasilUrai.judul.length - pilihan.length - (nomor >= 0 ? 1 : 0);
            if (dilewati > 0) {
                susunan.peringatan.unshift(`${dilewati} kolom paling kanan tidak dimasukkan karena tabel dibatasi ${MAKS_KOLOM} kolom. Untuk memilih kolom, pakai unggah berkas.`);
            }
            if (nomor >= 0) susunan.peringatan.push('Kolom "No" dilewati karena nomor urut dibuat otomatis.');
            this.pasang(tabel, susunan);
        },

        pasang(tabel, susunan) {
            this.cadangan = JSON.stringify(tabel);
            tabel.kolom.splice(0, tabel.kolom.length, ...susunan.kolom);
            tabel.baris.splice(0, tabel.baris.length, ...susunan.baris);
            this.hasil = `${susunan.ditambahkan} baris dimasukkan. Tabel sekarang ${susunan.baris.length} baris, ${susunan.kolom.length} kolom.`;
            this.peringatan = susunan.peringatan;
        },

        kembalikan(tabel) {
            const lama = JSON.parse(this.cadangan);
            tabel.kolom.splice(0, tabel.kolom.length, ...lama.kolom);
            tabel.baris.splice(0, tabel.baris.length, ...lama.baris);
            this.cadangan = null;
            this.peringatan = [];
            this.hasil = 'Tabel dikembalikan seperti sebelum impor.';
        },

        batal(pertahankanHasil = false) {
            lembar = [];
            data = null;
            this.namaSheet = [];
            this.kolomBerkas = [];
            this.pilihan = [];
            this.galat = '';
            if (!pertahankanHasil) {
                this.hasil = '';
                this.peringatan = [];
                this.cadangan = null;
            }
        },
    };
}
