<?php

namespace App\Support;

use App\Models\Tema;

/** Gaya pratinjau mini sampul dari warna tema (setara themeLook di desain). */
class PratinjauTema
{
    private const FONT = [
        'playfair' => "ui-serif,'New York',Georgia,serif",
        'jakarta' => "-apple-system,BlinkMacSystemFont,'SF Pro Display','Inter',system-ui,sans-serif",
        'script' => "'Snell Roundhand','Apple Chancery','Brush Script MT',cursive",
    ];

    public const TIER = [
        'Gratis' => 'background:#D7FFB8;color:#3C7A00;',
        'Premium' => 'background:#FFF4CC;color:#B25E00;',
        'Deluxe' => 'background:#262A31;color:#FFFFFF;',
    ];

    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luminans(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($v) {
            $q = $v / 255;

            return $q <= 0.03928 ? $q / 12.92 : (($q + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function rgba(string $hex, float $a): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return "rgba($r,$g,$b,$a)";
    }

    /** @return array<string,string> */
    public static function gaya(Tema $t): array
    {
        $sampul = strtoupper($t->warna_utama);
        $aksen = strtoupper($t->warna_aksen);
        $diSampul = self::luminans($sampul) > 0.42 ? '#10203A' : '#FFFFFF';
        $terang = self::luminans($sampul) > 0.6;
        $font = self::FONT[$t->font_judul] ?? self::FONT['playfair'];

        return [
            'bingkai' => 'border:2px solid #E2E5E9;background:'.strtoupper($t->warna_halaman).';',
            'sampul' => 'background:'.$sampul.';color:'.$diSampul.';',
            'kicker' => 'color:'.$aksen.';',
            'judul' => 'color:'.$diSampul.';font-family:'.$font.';',
            'tanggal' => 'color:'.$diSampul.';',
            'cincin' => 'border:1.5px solid '.self::rgba($aksen, 0.5).';',
            'tombol' => 'background:'.($terang ? strtoupper($t->warna_teks) : $diSampul).';color:'.($terang ? '#FFFFFF' : $sampul).';box-shadow:0 2px 0 '.$aksen.';',
            'badan' => 'background:'.strtoupper($t->warna_permukaan).';',
            'garis' => 'background:'.self::rgba($t->warna_teks, 0.5).';',
            'titik' => 'background:'.$aksen.';',
        ];
    }
}
