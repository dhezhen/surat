/**
 * Menyusun PDF dari gambar JPEG, satu gambar per halaman, tanpa pustaka tambahan.
 * JPEG disematkan apa adanya (DCTDecode), jadi ukuran PDF hampir sama dengan jumlah ukuran fotonya.
 * Lebar halaman disamakan dengan A4 (595 pt); tingginya mengikuti rasio foto.
 */

const LEBAR_A4 = 595.28;
const enc = new TextEncoder();

/**
 * @param {{ jpeg: Uint8Array, lebar: number, tinggi: number }[]} halaman
 * @returns {Uint8Array}
 */
export function susunPdf(halaman) {
    if (!halaman.length) throw new Error('Belum ada halaman untuk disusun.');

    const bagian = [];
    const offset = [];
    let panjang = 0;
    const tulis = (isi) => {
        const b = typeof isi === 'string' ? enc.encode(isi) : isi;
        bagian.push(b);
        panjang += b.length;
    };
    const objek = (nomor, isi, aliran = null) => {
        offset[nomor] = panjang;
        tulis(`${nomor} 0 obj\n${isi}\n`);
        if (aliran) {
            tulis('stream\n');
            tulis(aliran);
            tulis('\nendstream\n');
        }
        tulis('endobj\n');
    };

    // Nomor objek: 1 katalog, 2 daftar halaman, lalu tiga objek per halaman (halaman, isi, gambar).
    const nomorHalaman = halaman.map((_, i) => 3 + i * 3);

    tulis('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');
    objek(1, '<< /Type /Catalog /Pages 2 0 R >>');
    objek(2, `<< /Type /Pages /Kids [${nomorHalaman.map((n) => `${n} 0 R`).join(' ')}] /Count ${halaman.length} >>`);

    halaman.forEach((h, i) => {
        const n = nomorHalaman[i];
        const w = LEBAR_A4;
        const t = +(LEBAR_A4 * (h.tinggi / h.lebar)).toFixed(2);
        const isi = enc.encode(`q ${w} 0 0 ${t} 0 0 cm /Im0 Do Q`);

        objek(n, `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${w} ${t}] /Resources << /XObject << /Im0 ${n + 2} 0 R >> >> /Contents ${n + 1} 0 R >>`);
        objek(n + 1, `<< /Length ${isi.length} >>`, isi);
        objek(
            n + 2,
            `<< /Type /XObject /Subtype /Image /Width ${h.lebar} /Height ${h.tinggi} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${h.jpeg.length} >>`,
            h.jpeg,
        );
    });

    const jumlahObjek = 3 + halaman.length * 3;
    const awalXref = panjang;
    let xref = `xref\n0 ${jumlahObjek}\n0000000000 65535 f \n`;
    for (let n = 1; n < jumlahObjek; n++) xref += `${String(offset[n]).padStart(10, '0')} 00000 n \n`;
    tulis(xref);
    tulis(`trailer\n<< /Size ${jumlahObjek} /Root 1 0 R >>\nstartxref\n${awalXref}\n%%EOF\n`);

    const hasil = new Uint8Array(panjang);
    let posisi = 0;
    for (const b of bagian) {
        hasil.set(b, posisi);
        posisi += b.length;
    }

    return hasil;
}
