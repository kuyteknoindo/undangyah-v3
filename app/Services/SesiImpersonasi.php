<?php

namespace App\Services;

use App\Models\LogAksiAdmin;
use App\Models\User;
use App\Support\AksiAdminDitolak;
use Illuminate\Support\Facades\Auth;

/**
 * "Masuk sebagai" pengguna biasa untuk membantu dukungan. Dibatasi: hanya akun berperan pengguna yang aktif dan
 * terverifikasi, maksimal MENIT_MAKS menit, tercatat di log aksi admin, dan middleware PengamanAkun memblokir
 * halaman akun/pembelian selama sesi berjalan serta menampilkan tombol kembali.
 */
class SesiImpersonasi
{
    public const KUNCI = 'impersonasi';

    public const MENIT_MAKS = 30;

    public function aktif(): bool
    {
        return is_array(session(self::KUNCI));
    }

    /** @return array{admin_id:int,sampai:int,mulai:int}|null */
    public function data(): ?array
    {
        $d = session(self::KUNCI);

        return is_array($d) && isset($d['admin_id'], $d['sampai']) ? $d : null;
    }

    public function mulai(User $admin, User $target): void
    {
        abort_unless($admin->isAdmin() && ! $admin->ditangguhkan_at, 403);
        if ($this->aktif()) {
            throw new AksiAdminDitolak('Akhiri sesi "masuk sebagai" yang sedang berjalan lebih dulu.');
        }
        if ($admin->is($target)) {
            throw new AksiAdminDitolak('Kamu tidak bisa masuk sebagai dirimu sendiri.');
        }
        $target = User::findOrFail($target->id);
        if ($target->isAdmin()) {
            throw new AksiAdminDitolak('Tidak bisa masuk sebagai sesama admin.');
        }
        if ($target->ditangguhkan_at || ! $target->hasVerifiedEmail()) {
            throw new AksiAdminDitolak('Hanya akun aktif dengan email terverifikasi yang bisa dimasuki.');
        }

        LogAksiAdmin::catat($admin, 'impersonasi_mulai', $target);
        session([self::KUNCI => ['admin_id' => $admin->id, 'mulai' => now()->timestamp, 'sampai' => now()->addMinutes(self::MENIT_MAKS)->timestamp]]);
        Auth::login($target, false);
        session()->regenerate();
    }

    /** Kembali ke akun admin. Mengembalikan admin, atau null bila admin tak lagi sah (sesi diakhiri sepenuhnya). */
    public function akhiri(string $sebab = 'manual'): ?User
    {
        $d = $this->data();
        session()->forget(self::KUNCI);
        $target = Auth::user();
        $admin = $d ? User::find($d['admin_id']) : null;

        if ($admin && $admin->isAdmin() && ! $admin->ditangguhkan_at) {
            LogAksiAdmin::catat($admin, 'impersonasi_selesai', $target instanceof User ? $target : null, ['sebab' => $sebab]);
            Auth::login($admin, false);
            session()->regenerate();

            return $admin;
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return null;
    }

    public function sisaDetik(): int
    {
        $d = $this->data();

        return $d ? max(0, $d['sampai'] - now()->timestamp) : 0;
    }
}
