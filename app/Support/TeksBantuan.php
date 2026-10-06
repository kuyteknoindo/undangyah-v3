<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Format sederhana untuk isi artikel/dokumen: semua teks di-escape dulu, baru ditandai ulang.
 * Dikenali: paragraf (baris kosong), baris baru, daftar ("- butir"), dan **tebal**. Tidak ada HTML mentah dari masukan.
 */
class TeksBantuan
{
    public static function html(?string $teks): HtmlString
    {
        $teks = trim(str_replace(["\r\n", "\r"], "\n", (string) $teks));
        if ($teks === '') {
            return new HtmlString('');
        }

        $keluar = [];
        foreach (preg_split("/\n{2,}/", $teks) ?: [] as $blok) {
            $baris = explode("\n", $blok);
            if (collect($baris)->every(fn ($b) => str_starts_with(ltrim($b), '- '))) {
                $butir = array_map(fn ($b) => '<li>'.self::sebaris(substr(ltrim($b), 2)).'</li>', $baris);
                $keluar[] = '<ul class="list-disc pl-5">'.implode('', $butir).'</ul>';

                continue;
            }
            $keluar[] = '<p>'.implode('<br>', array_map(self::sebaris(...), $baris)).'</p>';
        }

        return new HtmlString(implode('', $keluar));
    }

    private static function sebaris(string $baris): string
    {
        $aman = e($baris);

        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $aman) ?? $aman;
    }
}
