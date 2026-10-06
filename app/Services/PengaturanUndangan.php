<?php

namespace App\Services;

use App\Models\Undangan;

/**
 * Pengaturan kecil milik satu fitur di `undangan.pengaturan[<kunci>]` yang berlaku seketika (tidak lewat terbit ulang),
 * mis. buka/tutup kolom ucapan. Penulisan tidak boleh memunculkan "ada perubahan belum diterbitkan" palsu:
 * bila sebelumnya draf sama dengan terbitan, salinan terbitan ikut diperbarui hanya untuk kunci ini.
 */
class PengaturanUndangan
{
    public function __construct(private PenerbitUndangan $penerbit) {}

    /** @param array<string,mixed> $bawaan */
    public function ambil(Undangan $u, string $kunci, array $bawaan = []): array
    {
        $nilai = $u->pengaturan[$kunci] ?? [];

        return array_replace($bawaan, is_array($nilai) ? $nilai : []);
    }

    /** @param array<string,mixed> $nilai */
    public function simpan(Undangan $u, string $kunci, array $nilai): void
    {
        $sinkron = $u->sudahTerbit() && is_array($u->terbitan) && ($u->terbitan['tanda'] ?? null) === $this->penerbit->tanda($u);

        $pengaturan = (array) $u->pengaturan;
        $pengaturan[$kunci] = $nilai;
        $u->pengaturan = $pengaturan;

        if ($sinkron) {
            $terbitan = (array) $u->terbitan;
            $terbitan['pengaturan'] = [...(array) ($terbitan['pengaturan'] ?? []), $kunci => $nilai];
            $terbitan['tanda'] = $this->penerbit->tanda($u);
            $u->terbitan = $terbitan;
        }
        $u->save();
    }
}
