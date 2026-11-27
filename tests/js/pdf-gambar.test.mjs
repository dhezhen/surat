import test from 'node:test';
import assert from 'node:assert/strict';
import { susunPdf } from '../../resources/js/pdf-gambar.js';

// Isi JPEG tidak diperiksa saat menyusun; byte acak cukup untuk menguji struktur PDF.
const gambar = (lebar, tinggi, ukuran = 50) => ({ jpeg: new Uint8Array(ukuran).fill(0xab), lebar, tinggi });
const teks = (pdf) => Buffer.from(pdf).toString('latin1');

test('offset xref menunjuk tepat ke tiap objek', () => {
    const pdf = teks(susunPdf([gambar(1000, 1414), gambar(800, 600)]));

    const awalXref = Number(pdf.match(/startxref\n(\d+)/)[1]);
    assert.equal(pdf.slice(awalXref, awalXref + 4), 'xref');

    const baris = pdf.slice(awalXref).split('\n');
    const jumlah = Number(baris[1].split(' ')[1]);
    assert.equal(jumlah, 3 + 2 * 3);
    for (let n = 1; n < jumlah; n++) {
        const posisi = Number(baris[2 + n].slice(0, 10));
        assert.ok(pdf.startsWith(`${n} 0 obj`, posisi), `objek ${n} di posisi ${posisi}`);
    }
});

test('halaman selebar A4 dengan tinggi mengikuti rasio foto', () => {
    const pdf = teks(susunPdf([gambar(1000, 2000), gambar(2000, 1000)]));
    assert.match(pdf, /\/Count 2/);
    assert.match(pdf, /\/MediaBox \[0 0 595\.28 1190\.56\]/);
    assert.match(pdf, /\/MediaBox \[0 0 595\.28 297\.64\]/);
    assert.match(pdf, /\/Width 1000 \/Height 2000 .*\/Filter \/DCTDecode \/Length 50/);
});

test('dapat dibuka pdf.js', async () => {
    const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const dok = await pdfjs.getDocument({ data: susunPdf([gambar(1000, 1414), gambar(1200, 900), gambar(900, 1200)]), isEvalSupported: false }).promise;
    assert.equal(dok.numPages, 3);
    const [, , lebar, tinggi] = (await dok.getPage(2)).view;
    assert.equal(lebar, 595.28);
    assert.equal(tinggi, 446.46);
});

test('tanpa halaman ditolak', () => {
    assert.throws(() => susunPdf([]), /Belum ada halaman/);
});
