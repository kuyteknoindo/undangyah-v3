<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\Undangan;

/**
 * Batas jumlah tamu per undangan. Mengikuti sakelar paket di KuotaUndangan: tanpa Pengaturan `paket.aktif`
 * atau tanpa nilai `paket.kuota_tamu.<paket>` (peta paket => jumlah) berarti tanpa batas.
 */
class KuotaTamu
{
    public function __construct(private KuotaUndangan $kuota) {}

    /** Batas tamu per undangan, atau null bila tanpa batas. */
    public function batas(Undangan $undangan): ?int
    {
        if (! $this->kuota->aktif() || ! $undangan->pengguna) {
            return null;
        }
        $nilai = Pengaturan::ambil('paket.kuota_tamu.'.$this->kuota->paket($undangan->pengguna));

        return is_numeric($nilai) ? max(0, (int) $nilai) : null;
    }

    public function terpakai(Undangan $undangan): int
    {
        return $undangan->tamu()->count();
    }

    /** Sisa slot tamu, atau null bila tanpa batas. */
    public function sisa(Undangan $undangan): ?int
    {
        $batas = $this->batas($undangan);

        return $batas === null ? null : max(0, $batas - $this->terpakai($undangan));
    }
}
