import { susunPdf } from './pdf-gambar';
import { SISI_DETEKSI, SUDUT_PENUH, deteksiSudut, luruskan, susutkan, ukuranLurus } from './potong';

/** Sisi terpanjang foto halaman. Cukup tajam untuk OCR, dan 10 halaman tetap jauh di bawah batas unggah 10 MB. */
const SISI_MAKS = 2200;
/** Foto asli disimpan lebih besar agar potongan dari bagian kecil foto tetap tajam. */
const SISI_ASLI = 3000;
const MUTU_JPEG = 0.85;
/** Porsi penyusutan sudut otomatis ke arah pusat (lihat susutkan di potong.js). */
const SUSUT_OTOMATIS = 0.03;

/** Kamera langsung di halaman hanya tersedia lewat HTTPS atau localhost. */
export function kameraLangsungTersedia() {
    return window.isSecureContext && !!navigator.mediaDevices?.getUserMedia;
}

export async function nyalakanKamera(video) {
    const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        // Kamera belakang dengan resolusi setinggi yang diizinkan perangkat.
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 3840 }, height: { ideal: 2160 } },
    });
    video.srcObject = stream;
    await video.play();

    return stream;
}

export function matikanKamera(stream, video) {
    stream?.getTracks().forEach((t) => t.stop());
    if (video) video.srcObject = null;
}

export function pesanGalatKamera(err) {
    switch (err?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Izin kamera ditolak. Izinkan kamera untuk situs ini di pengaturan browser, atau pakai tombol "Foto dengan aplikasi kamera".';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'Kamera tidak ditemukan di perangkat ini.';
        case 'NotReadableError':
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.';
        default:
            return 'Kamera tidak dapat dinyalakan.';
    }
}

function kanvasDari(sumber, lebarAsli, tinggiAsli, sisiMaks) {
    const skala = Math.min(1, sisiMaks / Math.max(lebarAsli, tinggiAsli));
    const kanvas = document.createElement('canvas');
    kanvas.width = Math.max(1, Math.round(lebarAsli * skala));
    kanvas.height = Math.max(1, Math.round(tinggiAsli * skala));
    const ctx = kanvas.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, kanvas.width, kanvas.height);
    ctx.drawImage(sumber, 0, 0, kanvas.width, kanvas.height);
    return kanvas;
}

const keJpeg = (kanvas, mutu = MUTU_JPEG) =>
    new Promise((selesai, gagal) => kanvas.toBlob((b) => (b ? selesai(b) : gagal(new Error('Foto gagal disimpan.'))), 'image/jpeg', mutu));

/** Mencari tepi kertas pada sumber apa pun (video, gambar, kanvas). Hasil 0..1 atau null. */
export function deteksiKertas(sumber, lebarAsli, tinggiAsli) {
    const kecil = kanvasDari(sumber, lebarAsli, tinggiAsli, SISI_DETEKSI);
    const data = kecil.getContext('2d').getImageData(0, 0, kecil.width, kecil.height).data;
    return deteksiSudut(data, kecil.width, kecil.height);
}

/** Meluruskan bagian kanvas asli di dalam sudut (0..1) menjadi halaman tegak. */
function potongKanvas(asli, sudut, susut) {
    const sudutPiksel = susutkan(sudut, susut).map((p) => ({ x: p.x * asli.width, y: p.y * asli.height }));
    const { lebar, tinggi } = ukuranLurus(sudutPiksel, SISI_MAKS);
    const sumber = asli.getContext('2d').getImageData(0, 0, asli.width, asli.height).data;
    const hasil = document.createElement('canvas');
    hasil.width = lebar;
    hasil.height = tinggi;
    hasil.getContext('2d').putImageData(new ImageData(luruskan(sumber, asli.width, asli.height, sudutPiksel, lebar, tinggi), lebar, tinggi), 0, 0);
    return hasil;
}

/**
 * Halaman = hasil potongan (yang diunggah) + foto asli (untuk memotong ulang).
 * cara: 'otomatis' (tepi kertas terdeteksi), 'utuh' (tidak terdeteksi), 'manual' (diatur pengguna).
 */
async function susunHalaman(asli, sudut, cara, asliBlob = null) {
    // Sudut otomatis disusutkan sedikit; sudut yang diatur tangan dipakai persis.
    const hasil = sudut ? potongKanvas(asli, sudut, cara === 'otomatis' ? SUSUT_OTOMATIS : 0) : kanvasDari(asli, asli.width, asli.height, SISI_MAKS);
    const blob = await keJpeg(hasil);

    return {
        blob,
        url: URL.createObjectURL(blob),
        lebar: hasil.width,
        tinggi: hasil.height,
        asli: { blob: asliBlob ?? (await keJpeg(asli, 0.9)), lebar: asli.width, tinggi: asli.height },
        sudut: sudut ?? SUDUT_PENUH,
        cara,
    };
}

async function keHalaman(sumber, lebarAsli, tinggiAsli) {
    const asli = kanvasDari(sumber, lebarAsli, tinggiAsli, SISI_ASLI);
    const sudut = deteksiKertas(asli, asli.width, asli.height);

    return susunHalaman(asli, sudut, sudut ? 'otomatis' : 'utuh');
}

/** Memotong ulang dari foto asli dengan sudut baru (null = foto utuh). Halaman lama tidak diubah. */
export async function potongUlang(halaman, sudut) {
    const gambar = await createImageBitmap(halaman.asli.blob);
    try {
        const asli = kanvasDari(gambar, gambar.width, gambar.height, SISI_ASLI);
        return await susunHalaman(asli, sudut, sudut ? 'manual' : 'utuh', halaman.asli.blob);
    } finally {
        gambar.close();
    }
}

export async function deteksiUlang(halaman) {
    const gambar = await createImageBitmap(halaman.asli.blob);
    try {
        return deteksiKertas(gambar, gambar.width, gambar.height);
    } finally {
        gambar.close();
    }
}

export function potretVideo(video) {
    if (!video.videoWidth) throw new Error('Kamera belum siap.');

    return keHalaman(video, video.videoWidth, video.videoHeight);
}

/** Foto dari aplikasi kamera ponsel (sering 12 MP lebih) diperkecil seperti hasil kamera langsung. */
export async function halamanDariFoto(file) {
    if (!/^image\/(jpe?g|png|webp|heic|heif)$/i.test(file.type)) throw new Error(`${file.name} bukan foto.`);
    // imageOrientation mengikuti EXIF agar foto ponsel yang diputar tidak tersimpan miring.
    const gambar = await createImageBitmap(file, { imageOrientation: 'from-image' });
    try {
        return await keHalaman(gambar, gambar.width, gambar.height);
    } finally {
        gambar.close();
    }
}

export function buangHalaman(halaman) {
    halaman.forEach((h) => URL.revokeObjectURL(h.url));
}

/** Satu halaman menjadi JPG, beberapa halaman digabung menjadi satu PDF. */
export async function berkasDariHalaman(halaman, namaDasar) {
    if (halaman.length === 1) {
        return new File([halaman[0].blob], `${namaDasar}.jpg`, { type: 'image/jpeg' });
    }
    const isi = await Promise.all(
        halaman.map(async (h) => ({ jpeg: new Uint8Array(await h.blob.arrayBuffer()), lebar: h.lebar, tinggi: h.tinggi })),
    );

    return new File([susunPdf(isi)], `${namaDasar}.pdf`, { type: 'application/pdf' });
}

export function namaPindaian() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');

    return `pindaian-${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}
