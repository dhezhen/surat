// PDF digambar ke canvas karena banyak browser ponsel tidak bisa menampilkan PDF di dalam halaman.

const KERAPATAN_MAKS = 2;

async function tampilkan(wadah) {
    const status = wadah.querySelector('[data-status]');
    const daftar = wadah.querySelector('[data-halaman]');
    try {
        // Build legacy dipakai agar ponsel dengan browser lama tetap bisa membuka pratinjau.
        const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
        pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url')).default;

        // PDF dari DomPDF tidak menyematkan font Times, jadi font pengganti diambil dari server sendiri.
        const pdf = await pdfjs.getDocument({
            url: wadah.dataset.pratinjauPdf,
            standardFontDataUrl: wadah.dataset.font,
            useSystemFonts: false,
        }).promise;

        for (let n = 1; n <= pdf.numPages; n++) {
            const halaman = await pdf.getPage(n);
            const lebarAsli = halaman.getViewport({ scale: 1 }).width;
            const kerapatan = Math.min(window.devicePixelRatio || 1, KERAPATAN_MAKS);
            const viewport = halaman.getViewport({ scale: (daftar.clientWidth / lebarAsli) * kerapatan });

            const kanvas = document.createElement('canvas');
            kanvas.width = Math.floor(viewport.width);
            kanvas.height = Math.floor(viewport.height);
            kanvas.className = 'block w-full border border-slate-300 bg-white';
            kanvas.setAttribute('role', 'img');
            kanvas.setAttribute('aria-label', `Halaman ${n} dari ${pdf.numPages}`);
            daftar.appendChild(kanvas);

            await halaman.render({ canvasContext: kanvas.getContext('2d'), viewport }).promise;
        }
        status.remove();
    } catch (e) {
        console.error(e);
        daftar.replaceChildren();
        status.textContent = 'Pratinjau tidak dapat ditampilkan. Gunakan tombol Buka PDF di atas.';
    }
}

document.querySelectorAll('[data-pratinjau-pdf]').forEach(tampilkan);
