<?php

namespace App\Services;

use App\Models\Paket;
use App\Models\Pengaturan;

/**
 * Menyalin kuota dari katalog paket ke Pengaturan `paket.kuota.<kode>` dan `paket.kuota_tamu.<kode>`, tempat
 * KuotaUndangan dan KuotaTamu membacanya. Kunci lain di `paket` (mis. `aktif`) dibiarkan apa adanya.
 */
class SinkronKuotaPaket
{
    public function sinkronkan(): void
    {
        $kuota = [];
        $tamu = [];
        foreach (Paket::query()->orderBy('urutan')->get(['kode', 'kuota_undangan', 'kuota_tamu']) as $paket) {
            $kuota[$paket->kode] = $paket->kuota_undangan;
            if ($paket->kuota_tamu !== null) {
                $tamu[$paket->kode] = $paket->kuota_tamu;
            }
        }

        $lama = Pengaturan::ambil('paket', []);
        Pengaturan::simpan('paket', array_merge(is_array($lama) ? $lama : [], ['kuota' => $kuota, 'kuota_tamu' => $tamu]));
    }
}
