<?php

namespace App\Support;

/** Menyamarkan nilai sensitif untuk log audit: nomor rekening hanya menyisakan 4 digit terakhir. */
class Samarkan
{
    public static function nomor(?string $nilai): ?string
    {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            return null;
        }
        $n = mb_strlen($nilai);

        return $n <= 4 ? str_repeat('*', $n) : str_repeat('*', $n - 4).mb_substr($nilai, -4);
    }
}
