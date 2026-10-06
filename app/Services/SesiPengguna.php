<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Mencabut sesi basis data milik satu pengguna (sandi berganti, akun diambil alih pendaftar sah). */
class SesiPengguna
{
    public static function cabutSemua(int $userId, ?string $kecuali = null): void
    {
        DB::table('sessions')->where('user_id', $userId)
            ->when($kecuali !== null, fn ($q) => $q->where('id', '!=', $kecuali))
            ->delete();
    }
}
