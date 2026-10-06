<?php

namespace App\Listeners;

use App\Services\KonfirmasiSandiAdmin;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/** Stempel konfirmasi sandi admin tidak boleh bertahan melewati masuk, keluar, atau reset sandi. */
class CabutKonfirmasiSandiAdmin
{
    public function handle(Login|Logout|PasswordReset $event): void
    {
        app(KonfirmasiSandiAdmin::class)->cabut();
    }
}
