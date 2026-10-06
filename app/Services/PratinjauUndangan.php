<?php

namespace App\Services;

use App\Models\Undangan;

/** Menentukan mode (terbit/draf/kosong) dan versi yang dipratinjau untuk satu undangan. Dipakai halaman dan bingkai. */
class PratinjauUndangan
{
    /**
     * @return array{mode:string,versi:string,adaDraf:bool} mode: terbit|draf|kosong; versi: terbit|draf|kosong
     */
    public static function tentukan(Undangan $u, ?string $versi = null): array
    {
        $isiAda = ! empty($u->isi);
        $terbit = $u->sudahTerbit() && ! empty($u->terbitan);
        $terbitanIsi = is_array($u->terbitan) ? ($u->terbitan['isi'] ?? null) : null;
        $adaDraf = $terbit && $isiAda && ($u->ada_perubahan || (is_array($terbitanIsi) && $terbitanIsi != $u->isi));

        $mode = $terbit ? 'terbit' : ($isiAda ? 'draf' : 'kosong');
        $pakai = $mode === 'terbit' ? ($adaDraf && $versi === 'draf' ? 'draf' : 'terbit') : $mode;

        return ['mode' => $mode, 'versi' => $pakai, 'adaDraf' => $adaDraf];
    }

    /** Contoh tamu untuk bagian "Tamu & QR" (dari isian contoh di editor). @return array{nama:string,alamat:string,kode:string} */
    public static function contohTamu(array $isi): array
    {
        return [
            'nama' => (string) ($isi['tmNama'] ?? ''),
            'alamat' => (string) ($isi['tmAlamat'] ?? ''),
            'kode' => (string) ($isi['tmKode'] ?? ''),
        ];
    }
}
