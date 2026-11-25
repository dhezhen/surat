import test from 'node:test';
import assert from 'node:assert/strict';
import { MAKS_BARIS, gabung, imporTabel, kolomBawaan, siapkan, tebakPemisah, teksSel, uraiTeks } from '../../resources/js/tabel-impor.js';

const kosong = () => ({ kolom: ['Nama', 'Keterangan'], baris: [['', '']] });

test('teks Excel dengan sel berkutip berisi baris baru dan tab', () => {
    assert.deepEqual(uraiTeks('A\tB\r\n"dua\nbaris"\t"kutip ""x"""\r\n', '\t'), [['A', 'B'], ['dua\nbaris', 'kutip "x"'], ['']]);
    assert.deepEqual(uraiTeks('tinggi 5" saja\tB', '\t'), [['tinggi 5" saja', 'B']]);
});

test('pemisah CSV ditebak dari baris judul', () => {
    assert.equal(tebakPemisah('Nama;NIM;"Alamat, lengkap"\n'), ';');
    assert.equal(tebakPemisah('Nama,NIM\n'), ',');
    assert.equal(tebakPemisah('Nama\tNIM\n'), '\t');
    assert.equal(tebakPemisah('Nama\n'), '\t');
});

test('angka panjang utuh, desimal memakai koma, tanggal dan jam terbaca', () => {
    assert.equal(teksSel({ angka: '3208123456789012345' }), '3208123456789012345');
    assert.equal(teksSel({ angka: '0.30000000000000004' }), '0,3');
    assert.equal(teksSel(new Date(Date.UTC(2026, 10, 25))), '25 November 2026');
    assert.equal(teksSel(new Date(Date.UTC(1899, 11, 30, 8, 30))), '08.30');
    assert.equal(teksSel(null), '');
});

test('baris dan kolom kosong dibuang, baris pertama menjadi judul', () => {
    const data = siapkan([['', 'Nama', '', 'NIM'], ['', '', '', ''], ['', 'Ani', '', '1']], true);
    assert.deepEqual(data, { judul: ['Nama', 'NIM'], baris: [['Ani', '1']], adaJudul: true });
});

test('satu baris saja diperlakukan sebagai data', () => {
    assert.equal(siapkan([['Ani', '1']], true).baris.length, 1);
});

test('kolom No berurutan dilewati, kolom No berisi lain dipertahankan', () => {
    assert.deepEqual(kolomBawaan(siapkan([['No', 'Nama'], ['1', 'Ani'], ['2', 'Budi']], true)), [1]);
    assert.deepEqual(kolomBawaan(siapkan([['No', 'Nama'], ['081234', 'Ani']], true)), [0, 1]);
});

test('kolom tabel menyesuaikan data impor', () => {
    const data = siapkan([['NIM', 'Nama', 'Kelas'], ['1', 'Ani', 'A']], true);
    const hasil = gabung(kosong(), data, { pilihan: [0, 1, 2], ganti: false });
    assert.deepEqual(hasil.kolom, ['NIM', 'Nama', 'Kelas']);
    assert.deepEqual(hasil.baris, [['1', 'Ani', 'A']]);
    assert.deepEqual(hasil.peringatan, []);
});

test('hanya kolom pilihan yang masuk', () => {
    const data = siapkan([['A', 'B', 'C'], ['1', '2', '3']], true);
    assert.deepEqual(gabung(kosong(), data, { pilihan: [2, 0], ganti: true }).baris, [['3', '1']]);
});

test('menambah di bawah data lama tidak menghapus apa pun', () => {
    const lama = { kolom: ['Nama', 'Ket'], baris: [['Ani', 'lama']] };
    const data = siapkan([['x', 'y', 'z'], ['Budi', 'baru', 'ekstra']], true);
    const hasil = gabung(lama, data, { pilihan: [0, 1, 2], ganti: false });
    assert.deepEqual(hasil.kolom, ['Nama', 'Ket', 'z']);
    assert.deepEqual(hasil.baris, [['Ani', 'lama', ''], ['Budi', 'baru', 'ekstra']]);
    assert.equal(hasil.peringatan.length, 1);
    assert.deepEqual(lama.baris, [['Ani', 'lama']]);
});

test('kelebihan baris dan sel panjang dilaporkan', () => {
    const data = siapkan([['A'], ...Array.from({ length: MAKS_BARIS + 5 }, () => ['x'.repeat(300)])], true);
    const hasil = gabung(kosong(), data, { pilihan: [0], ganti: true });
    assert.equal(hasil.baris.length, MAKS_BARIS);
    assert.equal(hasil.baris[0][0].length, 255);
    assert.match(hasil.peringatan.join(' '), /5 baris terakhir/);
    assert.match(hasil.peringatan.join(' '), /300 sel/);
});

test('tempel memberi peringatan kolom berlebih dan bisa dikembalikan', () => {
    const komponen = imporTabel();
    const tabel = kosong();
    const judul = Array.from({ length: 10 }, (_, i) => `K${i}`).join('\t');
    komponen.tempel(tabel, `${judul}\n${judul}`);
    assert.equal(tabel.kolom.length, 8);
    assert.match(komponen.peringatan[0], /2 kolom paling kanan/);
    komponen.kembalikan(tabel);
    assert.deepEqual(tabel, kosong());
});
