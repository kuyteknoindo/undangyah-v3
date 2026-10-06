<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;

/**
 * Batas undangan dan kunci tema menurut paket pengguna. Aktif hanya bila Pengaturan `paket.aktif` benar;
 * selama paket belum dikonfigurasi (Fase 6) semuanya tanpa batas.
 *
 * Pengaturan: paket.aktif (bool), paket.kuota (peta paket => jumlah, mis. {"gratis":1,"premium":3,"deluxe":10}).
 * Paket pengguna dibaca dari atribut `paket` (belum ada kolomnya: bawaan "gratis").
 */
class KuotaUndangan
{
    private const PERINGKAT = ['gratis' => 0, 'premium' => 1, 'deluxe' => 2];

    public function aktif(): bool
    {
        return (bool) Pengaturan::ambil('paket.aktif', false);
    }

    public function paket(User $user): string
    {
        $paket = strtolower((string) ($user->getAttribute('paket') ?: 'gratis'));

        return isset(self::PERINGKAT[$paket]) ? $paket : 'gratis';
    }

    /** Batas jumlah undangan, atau null bila tanpa batas. */
    public function batas(User $user): ?int
    {
        if (! $this->aktif()) {
            return null;
        }
        $kuota = Pengaturan::ambil('paket.kuota.'.$this->paket($user));

        return is_numeric($kuota) ? max(0, (int) $kuota) : null;
    }

    public function terpakai(User $user): int
    {
        return Undangan::where('user_id', $user->id)->count();
    }

    public function penuh(User $user): bool
    {
        $batas = $this->batas($user);

        return $batas !== null && $this->terpakai($user) >= $batas;
    }

    public function temaTerkunci(Tema $tema, User $user): bool
    {
        if (! $this->aktif()) {
            return false;
        }

        return (self::PERINGKAT[strtolower($tema->tier)] ?? 0) > self::PERINGKAT[$this->paket($user)];
    }
}
