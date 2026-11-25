const BULAN = {
    januari: 1, februari: 2, maret: 3, april: 4, mei: 5, juni: 6, juli: 7,
    agustus: 8, september: 9, oktober: 10, november: 11, desember: 12,
};
const POLA_BULAN = Object.keys(BULAN).join('|');
const POLA_TANGGAL = new RegExp(`(\\d{1,2})\\s*(${POLA_BULAN})\\s*(\\d{4})`, 'i');
const KATA_INSTANSI = /(universitas|institut|politeknik|sekolah|kementerian|kementrian|dinas|badan|pemerintah|yayasan|lembaga|fakultas|direktorat|biro|kantor|rektor|pt\.?\s|cv\.?\s)/i;
const KATA_SUB = /(fakultas|program\s+studi|lembaga|biro|direktorat|badan|unit|jurusan|departemen)/i;

const iso = (h, b, t) => {
    const bulan = BULAN[b.toLowerCase()];
    const hari = Number(h);
    if (!bulan || hari < 1 || hari > 31) return '';
    return `${t}-${String(bulan).padStart(2, '0')}-${String(hari).padStart(2, '0')}`;
};

const hurufBesar = (b) => {
    const huruf = b.replace(/[^A-Za-z]/g, '');
    return huruf.length >= 4 && huruf === huruf.toUpperCase();
};

const judul = (s) =>
    s.split(/\s+/).map((k) => (k.length <= 3 ? k : k.charAt(0) + k.slice(1).toLowerCase())).join(' ');

/**
 * Menebak metadata surat masuk dari teks hasil OCR. Hasilnya hanya saran: kolom yang tidak
 * terdeteksi dikembalikan kosong, dan pengguna tetap memeriksa sebelum menyimpan.
 */
export function ekstrakSurat(mentah) {
    const teks = String(mentah || '').replace(/\r/g, '').replace(/[ \t]+/g, ' ');
    const baris = teks.split('\n').map((b) => b.trim()).filter(Boolean);

    let nomor = '';
    const mNomor = teks.match(/(?:^|\n)\s*(?:Nomor|No\.)\s*[:.]?\s*([^\n]+)/i);
    if (mNomor) nomor = mNomor[1].replace(/^[:.\s]+/, '').trim();

    let perihal = '';
    const mHal = teks.match(/(?:^|\n)\s*(?:Perihal|Hal)\s*[:.]\s*([^\n]+)(\n[^\n]+)?/i);
    if (mHal) {
        perihal = mHal[1].trim();
        const lanjut = (mHal[2] || '').trim();
        if (lanjut && lanjut.length < 90 && !/^(Kepada|Yth|Lampiran|Nomor|Dengan|Di\s|Dalam|Sehubungan)/i.test(lanjut) && !/[:]\s*$/.test(lanjut)) {
            perihal += ' ' + lanjut;
        }
    }

    // Tanggal surat: utamakan pola "Kota, 19 November 2026"; kalau tidak ada, tanggal pertama di bagian atas.
    let tanggal = '';
    const mKota = teks.match(new RegExp(`(?:^|\\n)\\s*[A-Za-z .]{3,30},\\s*(\\d{1,2})\\s*(${POLA_BULAN})\\s*(\\d{4})`, 'i'));
    if (mKota) {
        tanggal = iso(mKota[1], mKota[2], mKota[3]);
    } else {
        const atas = baris.slice(0, 25).join('\n').match(POLA_TANGGAL) || teks.match(POLA_TANGGAL);
        if (atas) tanggal = iso(atas[1], atas[2], atas[3]);
    }

    // Pengirim: baris kop berhuruf kapital di bagian atas, beserta sub-unit di bawahnya.
    let pengirim = '';
    const atas = baris.slice(0, 12);
    const awal = atas.findIndex((b) => hurufBesar(b) && KATA_INSTANSI.test(b));
    if (awal >= 0) {
        const bagian = [atas[awal]];
        for (let i = awal + 1; i < atas.length && bagian.length < 3; i++) {
            if (hurufBesar(atas[i]) && KATA_SUB.test(atas[i])) bagian.push(atas[i]);
            else break;
        }
        pengirim = judul(bagian.join(', '));
    }

    const konteks = `${perihal} ${baris.slice(0, 40).join(' ')}`.toLowerCase();
    const pengirimKecil = pengirim.toLowerCase();
    let kategori = '';
    if (/undangan|mengundang/.test(konteks)) kategori = 'Undangan';
    else if (/rektor/.test(pengirimKecil) || (/^universitas[^,]*$/.test(pengirimKecil) && /rektor/.test(konteks))) kategori = 'Rektorat';
    else if (/fakultas|dekan/.test(pengirimKecil)) kategori = 'Fakultas';
    else if (pengirim) kategori = 'Instansi Luar';

    return {
        nomor_surat_asal: nomor,
        tanggal_surat: tanggal,
        pengirim,
        perihal: perihal.replace(/\s+/g, ' ').trim(),
        kategori,
    };
}
