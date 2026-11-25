// Tombol tema. Selama pengguna belum memilih, tampilan mengikuti pengaturan perangkat.
// Pasangan skrip ini di resources/views/layouts/_tema.blade.php memasang tema awal.

const KUNCI = 'tema';
const sistemGelap = window.matchMedia('(prefers-color-scheme: dark)');

// Penyimpanan browser bisa diblokir; tombol tetap bekerja untuk halaman yang sedang dibuka.
function pilihanTersimpan() {
    try {
        return localStorage.getItem(KUNCI);
    } catch {
        return null;
    }
}

function terapkan(gelap) {
    document.documentElement.classList.toggle('dark', gelap);
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-tombol-tema]')) return;

    const gelap = !document.documentElement.classList.contains('dark');
    terapkan(gelap);
    try {
        localStorage.setItem(KUNCI, gelap ? 'gelap' : 'terang');
    } catch {
        // Pilihan tidak tersimpan, tidak perlu mengganggu pengguna.
    }
});

sistemGelap.addEventListener('change', (e) => {
    if (!pilihanTersimpan()) terapkan(e.matches);
});

// Tab lain yang terbuka ikut berganti.
window.addEventListener('storage', (e) => {
    if (e.key !== KUNCI) return;
    terapkan(e.newValue ? e.newValue === 'gelap' : sistemGelap.matches);
});
