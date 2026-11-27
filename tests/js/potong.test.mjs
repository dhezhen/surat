import test from 'node:test';
import assert from 'node:assert/strict';
import { SUDUT_PENUH, deteksiSudut, homografi, luruskan, petakan, ukuranLurus, urutkanSudut } from '../../resources/js/potong.js';

const didalam = (sudut, x, y) => {
    // Segi empat cembung searah jarum jam di layar: titik di dalam bila di sisi yang sama dari keempat tepi.
    let tanda = 0;
    for (let i = 0; i < 4; i++) {
        const a = sudut[i], b = sudut[(i + 1) % 4];
        const s = Math.sign((b.x - a.x) * (y - a.y) - (b.y - a.y) * (x - a.x));
        if (s === 0) continue;
        if (tanda && s !== tanda) return false;
        tanda = s;
    }
    return true;
};

/** Foto buatan: alas, kertas berbentuk segi empat (piksel), baris teks gelap di dalam kertas, dan derau. */
function foto(lebar, tinggi, sudut, { alas = 45, kertas = 225, teks = true } = {}) {
    const rgba = new Uint8ClampedArray(lebar * tinggi * 4);
    let benih = 7;
    const acak = () => ((benih = (benih * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff);
    const [tl, , , bl] = sudut;
    for (let y = 0; y < tinggi; y++) {
        for (let x = 0; x < lebar; x++) {
            let v = alas;
            if (didalam(sudut, x + 0.5, y + 0.5)) {
                v = kertas;
                // Baris teks: pita gelap tipis setiap 9 piksel, mulai sedikit di bawah tepi atas.
                const baris = (y - (tl.y + bl.y) / 4) % 9;
                if (teks && baris > 0 && baris < 3 && x % 7 < 5) v = 60;
            }
            v += (acak() - 0.5) * 20;
            const o = (y * lebar + x) * 4;
            rgba[o] = rgba[o + 1] = rgba[o + 2] = v;
            rgba[o + 3] = 255;
        }
    }
    return rgba;
}

const dekat = (hasil, benar, lebar, tinggi, toleransi = 0.01) => {
    assert.ok(hasil, 'kertas harus terdeteksi');
    hasil.forEach((p, i) => {
        const dx = Math.abs(p.x - benar[i].x / lebar), dy = Math.abs(p.y - benar[i].y / tinggi);
        assert.ok(dx < toleransi && dy < toleransi, `sudut ${i}: dapat (${p.x.toFixed(3)}, ${p.y.toFixed(3)}), harusnya (${(benar[i].x / lebar).toFixed(3)}, ${(benar[i].y / tinggi).toFixed(3)})`);
    });
};

test('kertas tegak lurus terdeteksi', () => {
    const sudut = [{ x: 60, y: 40 }, { x: 340, y: 40 }, { x: 340, y: 260 }, { x: 60, y: 260 }];
    dekat(deteksiSudut(foto(400, 300, sudut), 400, 300), sudut, 400, 300);
});

test('kertas miring dan berperspektif terdeteksi, urutan sudut kiri-atas searah jarum jam', () => {
    const sudut = [{ x: 110, y: 30 }, { x: 330, y: 70 }, { x: 290, y: 280 }, { x: 50, y: 230 }];
    dekat(deteksiSudut(foto(400, 300, sudut), 400, 300), sudut, 400, 300);

    const trapesium = [{ x: 130, y: 50 }, { x: 270, y: 50 }, { x: 360, y: 270 }, { x: 40, y: 270 }];
    dekat(deteksiSudut(foto(400, 300, trapesium), 400, 300), trapesium, 400, 300);
});

test('kertas diputar hampir 45 derajat tetap terdeteksi', () => {
    const sudut = [{ x: 200, y: 20 }, { x: 330, y: 150 }, { x: 200, y: 280 }, { x: 70, y: 150 }];
    const hasil = deteksiSudut(foto(400, 300, sudut, { teks: false }), 400, 300);
    assert.ok(hasil);
    // Pada 45 derajat "kiri-atas" ambigu; cukup pastikan keempat sudut ditemukan.
    const ditemukan = sudut.every((s) => hasil.some((p) => Math.abs(p.x - s.x / 400) < 0.02 && Math.abs(p.y - s.y / 300) < 0.02));
    assert.ok(ditemukan, JSON.stringify(hasil));
});

test('alas terang atau kertas terlalu kecil: tidak ditebak', () => {
    const sudut = [{ x: 60, y: 40 }, { x: 340, y: 40 }, { x: 340, y: 260 }, { x: 60, y: 260 }];
    assert.equal(deteksiSudut(foto(400, 300, sudut, { alas: 230, kertas: 240 }), 400, 300), null);

    const kecil = [{ x: 180, y: 130 }, { x: 220, y: 130 }, { x: 220, y: 170 }, { x: 180, y: 170 }];
    assert.equal(deteksiSudut(foto(400, 300, kecil), 400, 300), null);
});

test('urutan sudut dari titik acak', () => {
    const acak = [{ x: 0.9, y: 0.95 }, { x: 0.1, y: 0.05 }, { x: 0.05, y: 0.9 }, { x: 0.95, y: 0.1 }];
    assert.deepEqual(urutkanSudut(acak), [acak[1], acak[3], acak[0], acak[2]]);
});

test('homografi memetakan sudut tujuan tepat ke sudut sumber', () => {
    const sumber = [{ x: 110, y: 30 }, { x: 330, y: 70 }, { x: 290, y: 280 }, { x: 50, y: 230 }];
    const h = homografi(sumber, 210, 297);
    [[0, 0], [210, 0], [210, 297], [0, 297]].forEach(([u, v], i) => {
        const p = petakan(h, u, v);
        assert.ok(Math.abs(p.x - sumber[i].x) < 1e-6 && Math.abs(p.y - sumber[i].y) < 1e-6);
    });
});

test('pelurusan persegi panjang tegak menyalin piksel apa adanya', () => {
    const lebar = 20, tinggi = 10;
    const sumber = new Uint8ClampedArray(lebar * tinggi * 4);
    for (let i = 0; i < lebar * tinggi; i++) sumber.set([i % 256, (i * 3) % 256, 7, 255], i * 4);

    const sudut = [{ x: 5, y: 2 }, { x: 15, y: 2 }, { x: 15, y: 8 }, { x: 5, y: 8 }];
    const hasil = luruskan(sumber, lebar, tinggi, sudut, 10, 6);
    for (let v = 0; v < 6; v++) {
        for (let u = 0; u < 10; u++) {
            const s = ((v + 2) * lebar + (u + 5)) * 4, o = (v * 10 + u) * 4;
            assert.deepEqual([...hasil.slice(o, o + 4)], [...sumber.slice(s, s + 3), 255], `piksel ${u},${v}`);
        }
    }
});

test('ukuran hasil mengikuti sisi terpanjang dan dibatasi', () => {
    const sudut = [{ x: 0, y: 0 }, { x: 1000, y: 0 }, { x: 900, y: 1414 }, { x: 100, y: 1414 }];
    assert.deepEqual(ukuranLurus(sudut, 5000), { lebar: 1000, tinggi: 1418 });
    assert.deepEqual(ukuranLurus(sudut, 709), { lebar: 500, tinggi: 709 });
    assert.equal(SUDUT_PENUH.length, 4);
});

test('susutkan menarik sudut ke pusat tanpa mengubah bentuk', async () => {
    const { susutkan } = await import('../../resources/js/potong.js');
    const hasil = susutkan([{ x: 0, y: 0 }, { x: 1, y: 0 }, { x: 1, y: 1 }, { x: 0, y: 1 }], 0.1);
    assert.deepEqual(hasil.map((p) => [+p.x.toFixed(3), +p.y.toFixed(3)]), [[0.05, 0.05], [0.95, 0.05], [0.95, 0.95], [0.05, 0.95]]);
});
