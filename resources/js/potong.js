/**
 * Deteksi tepi kertas dan pelurusan perspektif, tanpa pustaka (OpenCV.js terlalu besar untuk ponsel).
 *
 * Cara kerja deteksi, pada gambar kecil (sisi terpanjang ~400 px):
 * keabuan -> blur -> ambang Otsu -> komponen terang terbesar (kertas lebih terang dari alas)
 * -> selubung cembung -> dikurangi menjadi 4 sudut.
 * Gagal (alas terang, kertas memenuhi foto) -> null, dan pemanggil memakai foto utuh.
 *
 * Sudut selalu berurutan: kiri-atas, kanan-atas, kanan-bawah, kiri-bawah, dalam koordinat 0..1.
 */

/** @typedef {{ x: number, y: number }} Titik */

export const SISI_DETEKSI = 400;
const LUAS_MIN = 0.15;
const TEPI_MAKS = 0.5;

/** RGBA -> keabuan (luminans). */
export function keAbu(rgba, lebar, tinggi) {
    const abu = new Uint8ClampedArray(lebar * tinggi);
    for (let i = 0, j = 0; i < abu.length; i++, j += 4) {
        abu[i] = (rgba[j] * 77 + rgba[j + 1] * 150 + rgba[j + 2] * 29) >> 8;
    }
    return abu;
}

/** Blur kotak terpisah dua lintasan; meredam teks dan serat kertas agar kertas terbaca satu bidang. */
function blur(abu, lebar, tinggi, r = 2) {
    const tmp = new Uint8ClampedArray(abu.length);
    const out = new Uint8ClampedArray(abu.length);
    const lintas = (dari, ke, panjang, langkah, jumlah, jarak) => {
        for (let b = 0; b < jumlah; b++) {
            const awal = b * jarak;
            let total = 0;
            for (let k = -r; k <= r; k++) total += dari[awal + Math.min(panjang - 1, Math.max(0, k)) * langkah];
            for (let i = 0; i < panjang; i++) {
                ke[awal + i * langkah] = total / (2 * r + 1);
                total += dari[awal + Math.min(panjang - 1, i + r + 1) * langkah] - dari[awal + Math.max(0, i - r) * langkah];
            }
        }
    };
    lintas(abu, tmp, lebar, 1, tinggi, lebar);
    lintas(tmp, out, tinggi, lebar, lebar, 1);
    return out;
}

export function ambangOtsu(abu) {
    const hist = new Array(256).fill(0);
    for (const v of abu) hist[v]++;
    const total = abu.length;
    let jumlahSemua = 0;
    for (let i = 0; i < 256; i++) jumlahSemua += i * hist[i];

    let jumlahB = 0, bobotB = 0, terbaik = 0, ambang = 128;
    for (let t = 0; t < 256; t++) {
        bobotB += hist[t];
        if (!bobotB) continue;
        const bobotF = total - bobotB;
        if (!bobotF) break;
        jumlahB += t * hist[t];
        const rerataB = jumlahB / bobotB;
        const rerataF = (jumlahSemua - jumlahB) / bobotF;
        const antar = bobotB * bobotF * (rerataB - rerataF) ** 2;
        if (antar > terbaik) {
            terbaik = antar;
            ambang = t;
        }
    }
    return ambang;
}

/** Komponen terhubung terbesar dari piksel bertanda 1 (4-ketetanggaan). */
function komponenTerbesar(masker, lebar, tinggi) {
    const label = new Int32Array(masker.length);
    const antrean = new Int32Array(masker.length);
    let terbaik = { id: 0, luas: 0 };
    let id = 0;

    for (let mulai = 0; mulai < masker.length; mulai++) {
        if (!masker[mulai] || label[mulai]) continue;
        id++;
        let kepala = 0, ekor = 0, luas = 0;
        antrean[ekor++] = mulai;
        label[mulai] = id;
        while (kepala < ekor) {
            const p = antrean[kepala++];
            luas++;
            const x = p % lebar;
            const tetangga = [x > 0 ? p - 1 : -1, x < lebar - 1 ? p + 1 : -1, p - lebar, p + lebar];
            for (const q of tetangga) {
                if (q >= 0 && q < masker.length && masker[q] && !label[q]) {
                    label[q] = id;
                    antrean[ekor++] = q;
                }
            }
        }
        if (luas > terbaik.luas) terbaik = { id, luas };
    }
    return { label, ...terbaik };
}

const silang = (o, a, b) => (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);

/** Selubung cembung (monotone chain), berlawanan arah jarum jam dalam koordinat matematis. */
export function selubung(titik) {
    const t = [...titik].sort((a, b) => a.x - b.x || a.y - b.y);
    if (t.length < 3) return t;
    const bawah = [];
    for (const p of t) {
        while (bawah.length >= 2 && silang(bawah[bawah.length - 2], bawah[bawah.length - 1], p) <= 0) bawah.pop();
        bawah.push(p);
    }
    const atas = [];
    for (let i = t.length - 1; i >= 0; i--) {
        const p = t[i];
        while (atas.length >= 2 && silang(atas[atas.length - 2], atas[atas.length - 1], p) <= 0) atas.pop();
        atas.push(p);
    }
    return bawah.slice(0, -1).concat(atas.slice(0, -1));
}

/** Membuang titik selubung yang paling sedikit mengurangi luas, sampai tersisa empat. */
export function jadikanEmpat(poligon) {
    const p = [...poligon];
    while (p.length > 4) {
        let min = Infinity, buang = 0;
        for (let i = 0; i < p.length; i++) {
            const luas = Math.abs(silang(p[(i - 1 + p.length) % p.length], p[i], p[(i + 1) % p.length]));
            if (luas < min) {
                min = luas;
                buang = i;
            }
        }
        p.splice(buang, 1);
    }
    return p;
}

function potongGaris(a1, a2, b1, b2) {
    const d = (a1.x - a2.x) * (b1.y - b2.y) - (a1.y - a2.y) * (b1.x - b2.x);
    if (Math.abs(d) < 1e-9) return null;
    const t = ((a1.x - b1.x) * (b1.y - b2.y) - (a1.y - b1.y) * (b1.x - b2.x)) / d;
    return { x: a1.x + t * (a2.x - a1.x), y: a1.y + t * (a2.y - a1.y) };
}

/**
 * Blur membulatkan sudut kertas, dan sudut hasil jadikanEmpat jatuh sedikit ke dalam.
 * Tiap sisi diwakili tepi selubung terpanjang di antara dua sudut; sudut baru = perpotongan sisi bertetangga.
 */
export function pertajamSudut(hull, empat, diagonal) {
    if (empat.length !== 4) return empat;
    const indeks = empat.map((p) => hull.indexOf(p));
    if (indeks.includes(-1)) return empat;

    const sisi = indeks.map((awal, i) => {
        const akhir = indeks[(i + 1) % 4];
        let terpanjang = [hull[awal], hull[akhir]], panjang = 0;
        for (let j = awal; j !== akhir; j = (j + 1) % hull.length) {
            const a = hull[j], b = hull[(j + 1) % hull.length];
            const l = Math.hypot(a.x - b.x, a.y - b.y);
            if (l > panjang) {
                panjang = l;
                terpanjang = [a, b];
            }
        }
        return terpanjang;
    });

    return empat.map((p, i) => {
        const sebelum = sisi[(i + 3) % 4], sesudah = sisi[i];
        const q = potongGaris(sebelum[0], sebelum[1], sesudah[0], sesudah[1]);
        // Perpotongan yang melenceng jauh berarti sisinya tidak lurus; pakai sudut semula.
        return q && Math.hypot(q.x - p.x, q.y - p.y) < 0.08 * diagonal ? q : p;
    });
}

export function luasPoligon(p) {
    let s = 0;
    for (let i = 0; i < p.length; i++) {
        const a = p[i], b = p[(i + 1) % p.length];
        s += a.x * b.y - b.x * a.y;
    }
    return Math.abs(s) / 2;
}

/** Urutan kiri-atas, kanan-atas, kanan-bawah, kiri-bawah. */
export function urutkanSudut(sudut) {
    const cx = sudut.reduce((s, p) => s + p.x, 0) / sudut.length;
    const cy = sudut.reduce((s, p) => s + p.y, 0) / sudut.length;
    // Sumbu y menghadap ke bawah, jadi sudut atan2 yang naik berarti searah jarum jam di layar.
    const urut = [...sudut].sort((a, b) => Math.atan2(a.y - cy, a.x - cx) - Math.atan2(b.y - cy, b.x - cx));
    let awal = 0;
    urut.forEach((p, i) => {
        if (p.x + p.y < urut[awal].x + urut[awal].y) awal = i;
    });
    return [...urut.slice(awal), ...urut.slice(0, awal)];
}

export function cembung(sudut) {
    let tanda = 0;
    for (let i = 0; i < 4; i++) {
        const s = silang(sudut[i], sudut[(i + 1) % 4], sudut[(i + 2) % 4]);
        if (Math.abs(s) < 1e-9) return false;
        if (tanda && Math.sign(s) !== tanda) return false;
        tanda = Math.sign(s);
    }
    return true;
}

export const SUDUT_PENUH = [{ x: 0, y: 0 }, { x: 1, y: 0 }, { x: 1, y: 1 }, { x: 0, y: 1 }];

/**
 * Mencari empat sudut kertas pada gambar RGBA berukuran kecil.
 * @returns {Titik[] | null} sudut 0..1, atau null bila kertas tidak terdeteksi dengan yakin.
 */
export function deteksiSudut(rgba, lebar, tinggi) {
    const abu = blur(keAbu(rgba, lebar, tinggi), lebar, tinggi);
    const ambang = ambangOtsu(abu);
    const masker = new Uint8Array(abu.length);
    for (let i = 0; i < abu.length; i++) masker[i] = abu[i] > ambang ? 1 : 0;

    const { label, id, luas } = komponenTerbesar(masker, lebar, tinggi);
    if (!id || luas < LUAS_MIN * lebar * tinggi) return null;

    // Komponen yang menyentuh sebagian besar tepi foto biasanya alas terang, bukan kertas.
    let sentuh = 0;
    for (let x = 0; x < lebar; x++) sentuh += (label[x] === id) + (label[(tinggi - 1) * lebar + x] === id);
    for (let y = 0; y < tinggi; y++) sentuh += (label[y * lebar] === id) + (label[y * lebar + lebar - 1] === id);
    if (sentuh > TEPI_MAKS * 2 * (lebar + tinggi)) return null;

    // Cukup piksel paling kiri dan kanan tiap baris untuk menyusun selubung.
    const titik = [];
    for (let y = 0; y < tinggi; y++) {
        let kiri = -1, kanan = -1;
        for (let x = 0; x < lebar; x++) {
            if (label[y * lebar + x] === id) {
                if (kiri < 0) kiri = x;
                kanan = x;
            }
        }
        if (kiri >= 0) titik.push({ x: kiri, y }, { x: kanan + 1, y }, { x: kiri, y: y + 1 }, { x: kanan + 1, y: y + 1 });
    }

    const hull = selubung(titik);
    const empat = urutkanSudut(pertajamSudut(hull, jadikanEmpat(hull), Math.hypot(lebar, tinggi)));
    if (empat.length !== 4 || !cembung(empat)) return null;
    // Selubung yang jauh lebih luas dari isinya berarti bentuknya bukan segi empat (mis. kertas + bayangan).
    if (luasPoligon(empat) > luas * 1.25) return null;

    return empat.map((p) => ({ x: p.x / lebar, y: p.y / tinggi }));
}

const jarak = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

/**
 * Menarik sudut sedikit ke arah pusat. Tepi hasil deteksi bisa meleset satu-dua piksel ke luar,
 * dan tanpa ini sisa alas gelap tampak sebagai garis di tepi hasil potongan.
 */
export function susutkan(sudut, porsi) {
    const cx = sudut.reduce((s, p) => s + p.x, 0) / 4;
    const cy = sudut.reduce((s, p) => s + p.y, 0) / 4;
    return sudut.map((p) => ({ x: p.x + (cx - p.x) * porsi, y: p.y + (cy - p.y) * porsi }));
}

/** Ukuran hasil pelurusan dalam piksel: panjang sisi terpanjang dari tiap pasangan sisi yang berhadapan. */
export function ukuranLurus(sudutPiksel, sisiMaks) {
    const [tl, tr, br, bl] = sudutPiksel;
    let lebar = Math.max(jarak(tl, tr), jarak(bl, br));
    let tinggi = Math.max(jarak(tl, bl), jarak(tr, br));
    const skala = Math.min(1, sisiMaks / Math.max(lebar, tinggi));
    lebar = Math.max(1, Math.round(lebar * skala));
    tinggi = Math.max(1, Math.round(tinggi * skala));
    return { lebar, tinggi };
}

/**
 * Homografi yang memetakan persegi panjang tujuan (0,0)-(lebar,tinggi) ke segi empat sumber.
 * @returns {number[]} h0..h7 (h8 = 1)
 */
export function homografi(sudutSumber, lebar, tinggi) {
    const tujuan = [{ x: 0, y: 0 }, { x: lebar, y: 0 }, { x: lebar, y: tinggi }, { x: 0, y: tinggi }];
    const A = [];
    const b = [];
    for (let i = 0; i < 4; i++) {
        const { x: u, y: v } = tujuan[i];
        const { x, y } = sudutSumber[i];
        A.push([u, v, 1, 0, 0, 0, -u * x, -v * x]);
        b.push(x);
        A.push([0, 0, 0, u, v, 1, -u * y, -v * y]);
        b.push(y);
    }
    // Eliminasi Gauss dengan pivot parsial.
    for (let k = 0; k < 8; k++) {
        let pivot = k;
        for (let i = k + 1; i < 8; i++) if (Math.abs(A[i][k]) > Math.abs(A[pivot][k])) pivot = i;
        [A[k], A[pivot]] = [A[pivot], A[k]];
        [b[k], b[pivot]] = [b[pivot], b[k]];
        if (Math.abs(A[k][k]) < 1e-12) throw new Error('Sudut potongan tidak valid.');
        for (let i = k + 1; i < 8; i++) {
            const f = A[i][k] / A[k][k];
            for (let j = k; j < 8; j++) A[i][j] -= f * A[k][j];
            b[i] -= f * b[k];
        }
    }
    const h = new Array(8);
    for (let i = 7; i >= 0; i--) {
        let s = b[i];
        for (let j = i + 1; j < 8; j++) s -= A[i][j] * h[j];
        h[i] = s / A[i][i];
    }
    return h;
}

export function petakan(h, u, v) {
    const w = h[6] * u + h[7] * v + 1;
    return { x: (h[0] * u + h[1] * v + h[2]) / w, y: (h[3] * u + h[4] * v + h[5]) / w };
}

/**
 * Meluruskan segi empat pada gambar sumber (RGBA) menjadi persegi panjang, dengan interpolasi bilinear.
 * @param {Titik[]} sudutPiksel sudut dalam piksel sumber
 */
export function luruskan(sumber, lebarSumber, tinggiSumber, sudutPiksel, lebar, tinggi) {
    const h = homografi(sudutPiksel, lebar, tinggi);
    const hasil = new Uint8ClampedArray(lebar * tinggi * 4);
    const xMaks = lebarSumber - 1, yMaks = tinggiSumber - 1;

    for (let v = 0; v < tinggi; v++) {
        for (let u = 0; u < lebar; u++) {
            // Pusat piksel tujuan dipetakan ke sumber.
            const uu = u + 0.5, vv = v + 0.5;
            const w = h[6] * uu + h[7] * vv + 1;
            let x = (h[0] * uu + h[1] * vv + h[2]) / w - 0.5;
            let y = (h[3] * uu + h[4] * vv + h[5]) / w - 0.5;
            x = x < 0 ? 0 : x > xMaks ? xMaks : x;
            y = y < 0 ? 0 : y > yMaks ? yMaks : y;
            const x0 = x | 0, y0 = y | 0;
            const x1 = x0 < xMaks ? x0 + 1 : x0, y1 = y0 < yMaks ? y0 + 1 : y0;
            const fx = x - x0, fy = y - y0;
            const a = (y0 * lebarSumber + x0) * 4, b = (y0 * lebarSumber + x1) * 4;
            const c = (y1 * lebarSumber + x0) * 4, d = (y1 * lebarSumber + x1) * 4;
            const o = (v * lebar + u) * 4;
            for (let k = 0; k < 3; k++) {
                const atas = sumber[a + k] + (sumber[b + k] - sumber[a + k]) * fx;
                const bawah = sumber[c + k] + (sumber[d + k] - sumber[c + k]) * fx;
                hasil[o + k] = atas + (bawah - atas) * fy;
            }
            hasil[o + 3] = 255;
        }
    }
    return hasil;
}
