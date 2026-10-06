<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cara tunggal memilih pengguna menurut paket yang BERLAKU (paket berbayar yang habis masa aktifnya dihitung gratis,
 * sama seperti User::paketAktif()) dan menurut peran. Dipakai daftar Users dan siaran notifikasi admin.
 */
class SegmenPengguna
{
    public const SEMUA = 'semua';

    /** @param  Builder<User>  $q */
    public function paket(Builder $q, string $kode): Builder
    {
        $kode = strtolower($kode);
        if ($kode === self::SEMUA || $kode === '') {
            return $q;
        }

        if ($kode === 'gratis') {
            return $q->where(fn (Builder $w) => $w
                ->whereNull('paket')->orWhere('paket', 'gratis')
                ->orWhere(fn (Builder $x) => $x->whereNotNull('paket_sampai')->where('paket_sampai', '<', now())));
        }

        return $q->where('paket', $kode)->where(fn (Builder $w) => $w->whereNull('paket_sampai')->orWhere('paket_sampai', '>=', now()));
    }

    /** @param  Builder<User>  $q */
    public function peran(Builder $q, string $peran): Builder
    {
        return in_array($peran, [User::PERAN_USER, User::PERAN_ADMIN], true) ? $q->where('role', $peran) : $q;
    }

    /** Penerima siaran: email terverifikasi dan tidak ditangguhkan. @return Builder<User> */
    public function penerima(string $paket, string $peran): Builder
    {
        $q = User::query()->whereNotNull('email_verified_at')->whereNull('ditangguhkan_at');

        return $this->peran($this->paket($q, $paket), $peran);
    }
}
