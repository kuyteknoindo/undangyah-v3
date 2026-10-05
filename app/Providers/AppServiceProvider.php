<?php

namespace App\Providers;

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

        // Situs dilayani lewat Cloudflare (HTTPS); semua URL yang dibuat harus https.
        if (str_starts_with((string) config('app.url'), 'https://') && ! $this->app->runningUnitTests()) {
            URL::forceScheme('https');
        }
    }
}
