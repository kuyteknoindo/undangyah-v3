<?php

namespace App\Services;

use App\Models\Undangan;

/** Pengaturan kolom ucapan: `buka` (tamu boleh menulis) dan `setujui` (ucapan baru tersembunyi sampai disetujui). */
class BukuTamuPengaturan
{
    public const KUNCI = 'buku_tamu';

    public function __construct(private PengaturanUndangan $dasar) {}

    /** @return array{buka:bool,setujui:bool} */
    public function ambil(Undangan $u): array
    {
        $n = $this->dasar->ambil($u, self::KUNCI, ['buka' => true, 'setujui' => false]);

        return ['buka' => (bool) $n['buka'], 'setujui' => (bool) $n['setujui']];
    }

    public function simpan(Undangan $u, bool $buka, bool $setujui): void
    {
        $this->dasar->simpan($u, self::KUNCI, ['buka' => $buka, 'setujui' => $setujui]);
    }
}
