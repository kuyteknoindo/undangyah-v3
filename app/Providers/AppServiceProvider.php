<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PenahananSlug;
use App\Services\PenyimpanMedia;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        // Akun dihapus: undangannya ikut terhapus oleh cascade basis data (tanpa event model), jadi slug-nya ditahan di sini.
        User::deleting(function (User $user) {
            app(PenahananSlug::class)->tahanSemuaMilik($user);
        });

        // Baris media ikut terhapus oleh cascade basis data, tetapi berkasnya di disk tidak; hapus lewat model dulu.
        // Pendengar `deleting` yang mengembalikan nilai bukan-null menghentikan pendengar sesudahnya, jadi tidak ada yang dikembalikan.
        User::deleting(function (User $user) {
            app(PenyimpanMedia::class)->hapusMilik($user);
        });

        // Situs dilayani lewat Cloudflare (HTTPS); semua URL yang dibuat harus https.
        if (str_starts_with((string) config('app.url'), 'https://') && ! $this->app->runningUnitTests()) {
            URL::forceScheme('https');
        }
    }
}
