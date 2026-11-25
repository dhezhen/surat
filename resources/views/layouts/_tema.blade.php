{{-- Dijalankan di <head> sebelum CSS agar halaman tidak sempat berkedip terang saat mode gelap aktif. --}}
<script>
    (function () {
        var pilihan = null;
        try { pilihan = localStorage.getItem('tema'); } catch (e) {}
        var gelap = pilihan ? pilihan === 'gelap' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', gelap);
    })();
</script>
