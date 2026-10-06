<?php

namespace App\Providers;

use App\Models\User;
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

        // Baris media ikut terhapus oleh cascade basis data, tetapi berkasnya di disk tidak; hapus lewat model dulu.
        User::deleting(fn (User $user) => app(PenyimpanMedia::class)->hapusMilik($user));

        // Situs dilayani lewat Cloudflare (HTTPS); semua URL yang dibuat harus https.
        if (str_starts_with((string) config('app.url'), 'https://') && ! $this->app->runningUnitTests()) {
            URL::forceScheme('https');
        }
    }
}
