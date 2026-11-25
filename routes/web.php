<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PengaturanAiController;
use App\Http\Controllers\PengaturanAplikasiController;
use App\Http\Controllers\PengaturanSuratController;
use App\Http\Controllers\EksporController;
use App\Http\Controllers\JenisSuratController;
use App\Http\Controllers\PencarianController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuratKeluarController;
use App\Http\Controllers\SuratMasukController;
use App\Http\Controllers\ValidasiController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Rute publik (tanpa login) hanya logo serta validasi surat terbit beserta PDF-nya.
Route::get('/logo', [PengaturanAplikasiController::class, 'logo'])->name('logo');
Route::get('/validasi/{uuid}', [ValidasiController::class, 'show'])
    ->where('uuid', '[0-9a-fA-F-]{36}')
    ->name('validasi');
// Dibatasi karena setiap permintaan menyusun PDF baru.
Route::get('/validasi/{uuid}/pdf', [ValidasiController::class, 'pdf'])
    ->middleware('throttle:60,1')
    ->where('uuid', '[0-9a-fA-F-]{36}')
    ->name('validasi.pdf');

Route::middleware(['auth', 'aktif'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/pencarian', PencarianController::class)->name('pencarian');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/tanda-tangan', [ProfileController::class, 'tandaTangan'])->name('profile.tanda-tangan');
    Route::delete('/profile/tanda-tangan', [ProfileController::class, 'hapusTandaTangan'])->name('profile.tanda-tangan.hapus');

    Route::get('/ekspor/surat-keluar', [EksporController::class, 'suratKeluar'])->name('ekspor.surat-keluar');
    Route::get('/ekspor/surat-masuk', [EksporController::class, 'suratMasuk'])->name('ekspor.surat-masuk');

    // Kewenangan sebenarnya (status surat mana yang boleh dihapus) diperiksa di controller.
    Route::put('surat-keluar/{surat}/nomor', [SuratKeluarController::class, 'ubahNomor'])
        ->middleware('role:staff_prodi,super_admin')->name('surat-keluar.nomor')->whereNumber('surat');
    Route::put('surat-keluar/{surat}/pdf-publik', [SuratKeluarController::class, 'aturPdfPublik'])
        ->middleware('role:kaprodi,super_admin')->name('surat-keluar.pdf-publik')->whereNumber('surat');
    Route::delete('surat-keluar/{surat}', [SuratKeluarController::class, 'destroy'])
        ->middleware('role:staff_prodi,super_admin')->name('surat-keluar.destroy')->whereNumber('surat');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('pengaturan/ai', [PengaturanAiController::class, 'edit'])->name('pengaturan.ai');
        Route::put('pengaturan/ai', [PengaturanAiController::class, 'update'])->name('pengaturan.ai.update');
        Route::post('pengaturan/ai/uji', [PengaturanAiController::class, 'uji'])->middleware('throttle:6,1')->name('pengaturan.ai.uji');
        Route::get('pengaturan/surat', [PengaturanSuratController::class, 'edit'])->name('pengaturan.surat');
        Route::put('pengaturan/surat/identitas', [PengaturanSuratController::class, 'simpanIdentitas'])->name('pengaturan.surat.identitas');
        Route::put('pengaturan/surat/penomoran', [PengaturanSuratController::class, 'simpanPenomoran'])->name('pengaturan.surat.penomoran');
        Route::put('pengaturan/surat/kop-jenis', [PengaturanSuratController::class, 'modeKop'])->name('pengaturan.surat.kop.mode');
        Route::get('pengaturan/aplikasi', [PengaturanAplikasiController::class, 'edit'])->name('pengaturan.aplikasi');
        Route::put('pengaturan/aplikasi', [PengaturanAplikasiController::class, 'update'])->name('pengaturan.aplikasi.update');
        Route::post('pengaturan/aplikasi/logo', [PengaturanAplikasiController::class, 'simpanLogo'])->name('pengaturan.aplikasi.logo');
        Route::delete('pengaturan/aplikasi/logo', [PengaturanAplikasiController::class, 'hapusLogo'])->name('pengaturan.aplikasi.logo.hapus');
        Route::post('pengaturan/surat/kop', [PengaturanSuratController::class, 'simpanKop'])->name('pengaturan.surat.kop');
        Route::delete('pengaturan/surat/kop', [PengaturanSuratController::class, 'hapusKop'])->name('pengaturan.surat.kop.hapus');
        Route::post('pengaturan/surat/tanda-tangan/{pengguna}', [PengaturanSuratController::class, 'simpanTandaTangan'])->name('pengaturan.surat.ttd')->whereNumber('pengguna');
        Route::delete('pengaturan/surat/tanda-tangan/{pengguna}', [PengaturanSuratController::class, 'hapusTandaTangan'])->name('pengaturan.surat.ttd.hapus')->whereNumber('pengguna');
        Route::get('surat-keluar-terhapus', [SuratKeluarController::class, 'terhapus'])->name('surat-keluar.terhapus');
        Route::post('surat-keluar/{surat}/pulihkan', [SuratKeluarController::class, 'pulihkan'])->name('surat-keluar.pulihkan')->withTrashed()->whereNumber('surat');
        Route::resource('pengguna', PenggunaController::class)->except('show')->parameters(['pengguna' => 'pengguna']);
        Route::post('pengguna/{pengguna}/status', [PenggunaController::class, 'status'])->name('pengguna.status')->whereNumber('pengguna');
    });

    Route::middleware('role:staff_prodi')->group(function () {
        Route::post('ai/susun', [AiController::class, 'susun'])->middleware('throttle:12,1')->name('ai.susun');

        Route::resource('jenis-surat', JenisSuratController::class)->except('show')
            ->parameters(['jenis-surat' => 'jenisSurat']);

        Route::get('surat-keluar/buat', [SuratKeluarController::class, 'create'])->name('surat-keluar.create');
        Route::post('surat-keluar', [SuratKeluarController::class, 'store'])->name('surat-keluar.store');
        Route::get('surat-keluar/{surat}/ubah', [SuratKeluarController::class, 'edit'])->name('surat-keluar.edit')->whereNumber('surat');
        Route::put('surat-keluar/{surat}', [SuratKeluarController::class, 'update'])->name('surat-keluar.update')->whereNumber('surat');
        Route::post('surat-keluar/{surat}/ajukan', [SuratKeluarController::class, 'ajukan'])->name('surat-keluar.ajukan')->whereNumber('surat');

        Route::get('surat-masuk/buat', [SuratMasukController::class, 'create'])->name('surat-masuk.create');
        Route::post('surat-masuk', [SuratMasukController::class, 'store'])->name('surat-masuk.store');
        Route::get('surat-masuk/{surat}/ubah', [SuratMasukController::class, 'edit'])->name('surat-masuk.edit')->whereNumber('surat');
        Route::put('surat-masuk/{surat}', [SuratMasukController::class, 'update'])->name('surat-masuk.update')->whereNumber('surat');
        Route::delete('surat-masuk/{surat}', [SuratMasukController::class, 'destroy'])->name('surat-masuk.destroy')->whereNumber('surat');
    });

    Route::middleware('role:kaprodi')->group(function () {
        Route::post('surat-keluar/{surat}/setujui', [SuratKeluarController::class, 'setujui'])->name('surat-keluar.setujui')->whereNumber('surat');
        Route::post('surat-keluar/{surat}/revisi', [SuratKeluarController::class, 'revisi'])->name('surat-keluar.revisi')->whereNumber('surat');
    });

    // Staff & Kaprodi
    Route::get('surat-keluar', [SuratKeluarController::class, 'index'])->name('surat-keluar.index');
    Route::get('surat-keluar/{surat}', [SuratKeluarController::class, 'show'])->name('surat-keluar.show')->whereNumber('surat');
    Route::get('surat-keluar/{surat}/pdf', [SuratKeluarController::class, 'pdf'])->name('surat-keluar.pdf')->whereNumber('surat');

    Route::get('surat-masuk', [SuratMasukController::class, 'index'])->name('surat-masuk.index');
    Route::get('surat-masuk/{surat}', [SuratMasukController::class, 'show'])->name('surat-masuk.show')->whereNumber('surat');
    Route::get('surat-masuk/{surat}/berkas', [SuratMasukController::class, 'berkas'])->name('surat-masuk.berkas')->whereNumber('surat');
});

require __DIR__.'/auth.php';
