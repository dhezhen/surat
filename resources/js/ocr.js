import { ekstrakSurat } from './ocr-parser';

const TEKS_DIGITAL_MIN = 120;
const SKALA_PDF = 2.2;

const STATUS = {
    'loading tesseract core': 'Memuat mesin OCR',
    'initializing tesseract': 'Menyiapkan mesin OCR',
    'loading language traineddata': 'Memuat data bahasa Indonesia',
    'initializing api': 'Menyiapkan pembaca',
    'recognizing text': 'Membaca teks',
};

/** Halaman pertama dan terakhir saja: kop ada di depan, tanggal dan tanda tangan di belakang. */
function halamanTarget(jumlah) {
    return jumlah > 1 ? [1, jumlah] : [1];
}

async function bacaPdf(file, lapor) {
    const pdfjs = await import('pdfjs-dist');
    const workerUrl = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
    pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

    lapor('Membuka PDF', 0.05);
    const pdf = await pdfjs.getDocument({ data: await file.arrayBuffer() }).promise;
    const nomor = halamanTarget(pdf.numPages);

    // PDF digital sudah punya lapisan teks; memakainya lebih cepat dan lebih akurat daripada OCR.
    let digital = '';
    for (const n of nomor) {
        const halaman = await pdf.getPage(n);
        const isi = await halaman.getTextContent();
        digital += isi.items.map((i) => i.str + (i.hasEOL ? '\n' : ' ')).join('') + '\n';
    }
    if (digital.replace(/\s/g, '').length >= TEKS_DIGITAL_MIN) return { teks: digital, metode: 'teks digital' };

    const kanvas = [];
    for (const n of nomor) {
        const halaman = await pdf.getPage(n);
        const viewport = halaman.getViewport({ scale: SKALA_PDF });
        const c = document.createElement('canvas');
        c.width = Math.floor(viewport.width);
        c.height = Math.floor(viewport.height);
        await halaman.render({ canvasContext: c.getContext('2d'), viewport }).promise;
        kanvas.push(c);
    }

    return { sumber: kanvas, metode: 'OCR' };
}

async function ocr(sumber, basis, lapor) {
    const { createWorker } = await import('tesseract.js');
    let bagian = 0;
    let total = sumber.length;

    const worker = await createWorker('ind', 1, {
        workerPath: `${basis}/worker.min.js`,
        corePath: basis,
        langPath: basis,
        gzip: false,
        logger: (m) => {
            const label = STATUS[m.status];
            if (!label) return;
            const kemajuan = m.status === 'recognizing text' ? (bagian + m.progress) / total : 0;
            lapor(label, 0.1 + 0.9 * (m.status === 'recognizing text' ? kemajuan : 0.05));
        },
    });

    try {
        let teks = '';
        for (const s of sumber) {
            const { data } = await worker.recognize(s);
            teks += data.text + '\n';
            bagian++;
        }
        return teks;
    } finally {
        await worker.terminate();
    }
}

/** Membaca foto halaman hasil kamera (halaman pertama dan terakhir saja, seperti PDF). */
export async function bacaGambar(gambar, basis, lapor = () => {}) {
    const target = halamanTarget(gambar.length).map((n) => gambar[n - 1]);
    const teks = await ocr(target, basis, lapor);

    return { teks: teks.trim(), metode: 'OCR', hasil: ekstrakSurat(teks) };
}

/**
 * Membaca berkas scan (PDF/JPG/PNG) di browser dan menebak metadata surat masuk.
 * Berkas tidak meninggalkan komputer: mesin OCR dan data bahasa dilayani dari server sendiri.
 */
export async function bacaBerkas(file, basis, lapor = () => {}) {
    let teks = '';
    let metode = 'OCR';

    if (file.type === 'application/pdf') {
        const hasil = await bacaPdf(file, lapor);
        metode = hasil.metode;
        teks = hasil.teks ?? (await ocr(hasil.sumber, basis, lapor));
    } else if (/^image\/(png|jpe?g)$/.test(file.type)) {
        teks = await ocr([file], basis, lapor);
    } else {
        throw new Error('Format berkas harus PDF, JPG, atau PNG.');
    }

    return { teks: teks.trim(), metode, hasil: ekstrakSurat(teks) };
}
