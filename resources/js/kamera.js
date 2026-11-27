import { susunPdf } from './pdf-gambar';

/** Sisi terpanjang foto halaman. Cukup tajam untuk OCR, dan 10 halaman tetap jauh di bawah batas unggah 10 MB. */
const SISI_MAKS = 2200;
const MUTU_JPEG = 0.85;

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

/** Menggambar sumber (video atau gambar) ke kanvas berukuran wajar, lalu menyimpannya sebagai halaman JPEG. */
async function keHalaman(sumber, lebarAsli, tinggiAsli) {
    const skala = Math.min(1, SISI_MAKS / Math.max(lebarAsli, tinggiAsli));
    const lebar = Math.round(lebarAsli * skala);
    const tinggi = Math.round(tinggiAsli * skala);

    const kanvas = document.createElement('canvas');
    kanvas.width = lebar;
    kanvas.height = tinggi;
    const ctx = kanvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, lebar, tinggi);
    ctx.drawImage(sumber, 0, 0, lebar, tinggi);

    const blob = await new Promise((selesai, gagal) =>
        kanvas.toBlob((b) => (b ? selesai(b) : gagal(new Error('Foto gagal disimpan.'))), 'image/jpeg', MUTU_JPEG),
    );

    return { blob, lebar, tinggi, url: URL.createObjectURL(blob) };
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
