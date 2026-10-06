<?php

namespace App\Support;

class NomorWa
{
    /** Terima 08xx, 8xx, +62 8xx atau 62 8xx; kembalikan 8xx tanpa awalan (string kosong bila kosong). */
    public static function ringkas(?string $nomor): string
    {
        $angka = preg_replace('/\D/', '', (string) $nomor) ?? '';

        return preg_replace('/^(62|0)/', '', $angka) ?? '';
    }

    /** Nomor tersimpan (62812...) menjadi "+62 812 3456 7890"; null bila kosong. */
    public static function tampil(?string $tersimpan): ?string
    {
        $inti = self::ringkas($tersimpan);
        if ($inti === '') {
            return null;
        }

        return '+62 '.trim(preg_replace('/^(\d{3})(\d{4})(\d*)$/', '$1 $2 $3', $inti) ?? $inti);
    }
}
