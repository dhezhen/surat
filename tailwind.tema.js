/*
 * Warna yang berganti antara mode terang dan gelap, satu tempat untuk keduanya.
 * Format tiap entri: [terang, gelap]. Nilai terang sama dengan bawaan Tailwind (indigo: navy aplikasi),
 * jadi tampilan terang tidak berubah. Kelompok dipisah per properti karena satu warna bisa berperan ganda:
 * "white" sebagai latar menjadi gelap, tetapi teks putih di atas tombol harus tetap putih.
 */
import plugin from 'tailwindcss/plugin';

const latar = {
    white: ['#ffffff', '#222a3b'],
    slate: {
        50: ['#f8fafc', '#273044'],
        100: ['#f1f5f9', '#2c364c'],
        200: ['#e2e8f0', '#36415a'],
        300: ['#cbd5e1', '#4a5672'],
    },
    gray: {
        50: ['#f9fafb', '#273044'],
        100: ['#f3f4f6', '#2c364c'],
        500: ['#6b7280', '#05070c'], // hanya dipakai tirai modal
    },
    indigo: {
        50: ['#eef2f9', '#26345a'],
        100: ['#dbe4f3', '#2e3f6b'],
        700: ['#1e3368', '#4b6aaf'],
        800: ['#162752', '#3f5c9d'],
        900: ['#0f1c3c', '#3f5c9d'],
    },
    red: { 50: ['#fef2f2', '#3a1f27'] },
    amber: { 50: ['#fffbeb', '#382b14'], 100: ['#fef3c7', '#473617'] },
    emerald: { 50: ['#ecfdf5', '#14312b'] },
};

const netralTeks = {
    500: ['#64748b', '#9aa5ba'],
    600: ['#475569', '#b3bccd'],
    700: ['#334155', '#cfd6e2'],
    800: ['#1e293b', '#e3e8f0'],
    900: ['#0f172a', '#f1f4f9'],
};

const teks = {
    slate: netralTeks,
    gray: {
        500: ['#6b7280', '#9aa5ba'],
        600: ['#4b5563', '#b3bccd'],
        700: ['#374151', '#cfd6e2'],
        800: ['#1f2937', '#e3e8f0'],
        900: ['#111827', '#f1f4f9'],
    },
    indigo: {
        600: ['#2c4682', '#9ab2e6'],
        700: ['#1e3368', '#a5bbea'],
        800: ['#162752', '#b4c7ef'],
        900: ['#0f1c3c', '#cddaf5'],
    },
    red: {
        600: ['#dc2626', '#fca5a5'],
        700: ['#b91c1c', '#fca5a5'],
        800: ['#991b1b', '#fecaca'],
        900: ['#7f1d1d', '#fecaca'],
    },
    amber: { 800: ['#92400e', '#fcd34d'], 900: ['#78350f', '#fde68a'] },
    emerald: { 800: ['#065f46', '#6ee7b7'], 900: ['#064e3b', '#a7f3d0'] },
    green: { 600: ['#16a34a', '#4ade80'] },
};

const garis = {
    DEFAULT: ['#e5e7eb', '#38445c'],
    slate: {
        100: ['#f1f5f9', '#2e384d'],
        200: ['#e2e8f0', '#38445c'],
        300: ['#cbd5e1', '#66738f'], // tepi kolom isian: minimal 3:1 di atas latar gelap
        400: ['#94a3b8', '#7a87a3'],
    },
    gray: { 300: ['#d1d5db', '#66738f'] },
    indigo: {
        300: ['#8ea5d2', '#55709f'],
        400: ['#5f7bb8', '#7d98d0'],
        800: ['#162752', '#8ea5d2'],
    },
    red: { 200: ['#fecaca', '#7f2d36'], 300: ['#fca5a5', '#a13c47'] },
    amber: { 200: ['#fde68a', '#6b5320'], 300: ['#fcd34d', '#8a6a22'], 500: ['#f59e0b', '#d99a1e'] },
    emerald: { 200: ['#a7f3d0', '#1f5c4a'], 300: ['#6ee7b7', '#2a7a62'] },
};

const cincin = {
    slate: { 300: ['#cbd5e1', '#66738f'] },
    indigo: { 500: ['#3f5c9d', '#8ea5d2'], 700: ['#1e3368', '#8ea5d2'] },
    red: { 300: ['#fca5a5', '#a13c47'] },
    amber: { 300: ['#fcd34d', '#8a6a22'] },
    emerald: { 300: ['#6ee7b7', '#2a7a62'] },
};

// Celah antara elemen terfokus dan cincinnya mengikuti warna permukaan.
const celahCincin = { DEFAULT: ['#ffffff', '#222a3b'] };

const keRgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)).join(' ');

// Mengubah pohon [terang, gelap] menjadi warna Tailwind berbasis variabel, sambil mencatat nilai variabelnya.
function susun(pohon, jalur, terang, gelap) {
    return Object.fromEntries(Object.entries(pohon).map(([kunci, nilai]) => {
        const nama = [...jalur, kunci];
        if (!Array.isArray(nilai)) return [kunci, susun(nilai, nama, terang, gelap)];

        const variabel = `--${nama.join('-')}`;
        terang[variabel] = keRgb(nilai[0]);
        gelap[variabel] = keRgb(nilai[1]);

        return [kunci, `rgb(var(${variabel}) / <alpha-value>)`];
    }));
}

const kertasTerang = {};
const kertasGelap = {};
const antarmukaTerang = {};
const antarmukaGelap = {};

export const warnaTema = {
    backgroundColor: susun(latar, ['latar'], kertasTerang, kertasGelap),
    textColor: susun(teks, ['teks'], kertasTerang, kertasGelap),
    borderColor: susun(garis, ['garis'], kertasTerang, kertasGelap),
    ringColor: susun(cincin, ['cincin'], antarmukaTerang, antarmukaGelap),
    ringOffsetColor: susun(celahCincin, ['celah'], antarmukaTerang, antarmukaGelap),
};

// Nilai DEFAULT ini disalin Tailwind apa adanya ke CSS dasar, sehingga tidak boleh memuat <alpha-value>.
warnaTema.ringOffsetColor.DEFAULT = 'rgb(var(--celah-DEFAULT))';

/*
 * .tema-terang mengunci latar, teks, dan garis pada nilai terang (kertas surat, gambar tanda tangan, logo).
 * Cincin fokus sengaja tidak dikunci karena digambar di luar elemen, di atas latar halaman.
 * Nilai gelap hanya berlaku di layar supaya hasil cetak tetap terang.
 */
export const variabelTema = plugin(({ addBase }) => {
    addBase({
        ':root': { ...kertasTerang, ...antarmukaTerang },
        '.tema-terang': { ...kertasTerang, colorScheme: 'light' },
        '@media screen': {
            'html.dark': { ...kertasGelap, ...antarmukaGelap, colorScheme: 'dark' },
            'html.dark .tema-terang': { ...kertasTerang, colorScheme: 'light' },
        },
    });
});
