<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use App\Services\KonfirmasiSandiAdmin;

/** Pembantu tes: tandai admin sudah mengonfirmasi kata sandi (aksi sensitif admin) tanpa lewat dialog. */
trait KonfirmasiSandiTes
{
    protected function sudahKonfirmasiSandi(User $admin, ?int $pada = null): User
    {
        session()->put(KonfirmasiSandiAdmin::KUNCI, app(KonfirmasiSandiAdmin::class)->stempel($admin, $pada));

        return $admin;
    }

    /** Untuk permintaan HTTP biasa (controller): `$this->withSession($this->sesiKonfirmasi($admin))->get(...)`. */
    protected function sesiKonfirmasi(User $admin, ?int $pada = null): array
    {
        return [KonfirmasiSandiAdmin::KUNCI => app(KonfirmasiSandiAdmin::class)->stempel($admin, $pada)];
    }
}
