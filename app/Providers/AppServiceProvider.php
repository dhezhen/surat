<?php

namespace App\Providers;

use App\Support\PengaturanEmail;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pengaturan SMTP dibaca saat email pertama akan dikirim, bukan di setiap permintaan.
        $this->app->afterResolving(MailManager::class, fn () => PengaturanEmail::terapkan());
    }
}
