<?php

namespace App\Services;

use App\Models\Lagu;

/** Jembatan katalog musik ke editor (opsi pilihan `musLagu`) dan renderer (lagu terpilih). */
class KatalogLagu
{
    /** Opsi untuk bidang select `musLagu`: [nilai, label]. Hanya lagu berstatus publik, mengikuti urutan katalog. @return list<array{0:string,1:string}> */
    public static function opsiEditor(): array
    {
        $opsi = [['', 'Belum memilih lagu']];
        try {
            foreach (Lagu::publik()->urut()->get() as $l) {
                $opsi[] = [$l->kode, implode(' · ', array_filter([$l->judul, $l->keterangan ?: $l->artis, $l->durasi > 0 ? $l->durasiTeks() : null]))];
            }
        } catch (\Throwable) {
            // Tabel belum ada (mis. sebelum migrasi): editor tetap jalan tanpa pilihan lagu.
        }

        return $opsi;
    }

    /** Lagu publik menurut kode, atau null (tidak ada, draf, atau basis data tak tersedia). */
    public static function cari(string $kode): ?Lagu
    {
        if ($kode === '' || strlen($kode) > 20) {
            return null;
        }
        try {
            return Lagu::publik()->with('media')->where('kode', $kode)->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
