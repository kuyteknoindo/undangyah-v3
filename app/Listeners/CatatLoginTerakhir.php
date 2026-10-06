<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/** Mengisi users.login_terakhir_at (kolom ditampilkan di panel admin). Tidak memicu updated_at. */
class CatatLoginTerakhir
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            User::whereKey($event->user->getKey())->toBase()->update(['login_terakhir_at' => now()]);
        }
    }
}
