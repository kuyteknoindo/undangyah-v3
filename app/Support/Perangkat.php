<?php

namespace App\Support;

/** Nama perangkat ramah-baca dari User-Agent ("Chrome di Windows"). */
class Perangkat
{
    public static function nama(?string $ua): string
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return 'Perangkat tidak dikenal';
        }

        $peramban = match (true) {
            (bool) preg_match('~Edg(e|A|iOS)?/~', $ua) => 'Edge',
            (bool) preg_match('~OPR/|Opera~', $ua) => 'Opera',
            (bool) preg_match('~SamsungBrowser~', $ua) => 'Samsung Internet',
            (bool) preg_match('~Firefox/|FxiOS~', $ua) => 'Firefox',
            (bool) preg_match('~Chrome/|CriOS~', $ua) => 'Chrome',
            (bool) preg_match('~Safari/~', $ua) => 'Safari',
            default => 'Peramban',
        };

        $sistem = match (true) {
            (bool) preg_match('~Android~', $ua) => 'Android',
            (bool) preg_match('~iPhone|iPad|iPod~', $ua) => 'iOS',
            (bool) preg_match('~Windows~', $ua) => 'Windows',
            (bool) preg_match('~Mac OS X|Macintosh~', $ua) => 'macOS',
            (bool) preg_match('~CrOS~', $ua) => 'ChromeOS',
            (bool) preg_match('~Linux~', $ua) => 'Linux',
            default => null,
        };

        return $sistem ? "{$peramban} di {$sistem}" : $peramban;
    }
}
