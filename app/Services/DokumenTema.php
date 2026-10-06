<?php

namespace App\Services;

/**
 * Format dan validasi dokumen tema (kolom `tema.dokumen`) serta pemeriksaan kualitas tema.
 *
 * Dokumen yang dikenali renderer sengaja kecil dan hanya berisi angka/enum tervalidasi:
 *   {"global": {"base": 12..20}}   ukuran teks dasar (px); 14 = ukuran bawaan renderer
 * Kunci lain dibuang. Tidak ada HTML/CSS/URL bebas dari admin yang sampai ke keluaran publik.
 */
class DokumenTema
{
    public const FONT = ['playfair' => 'Serif klasik', 'jakarta' => 'Sans modern', 'script' => 'Script'];

    public const BASE_MIN = 12;

    public const BASE_MAX = 20;

    public const BASE_BAWAAN = 14;

    public const WARNA = [
        'warna_utama' => 'Warna utama (sampul)',
        'warna_aksen' => 'Aksen',
        'warna_permukaan' => 'Permukaan (kartu)',
        'warna_halaman' => 'Latar halaman',
        'warna_teks' => 'Teks',
    ];

    public const BAWAAN = [
        'warna_utama' => '#1D4ED8', 'warna_aksen' => '#C99A3B', 'warna_permukaan' => '#F3F6FB', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#10203A',
    ];

    /** Palet cepat (dari desain). Kunci = kolom tema. */
    public const PALET = [
        ['Biru Klasik', ['warna_utama' => '#1D4ED8', 'warna_aksen' => '#C99A3B', 'warna_permukaan' => '#F3F6FB', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#10203A']],
        ['Hijau Sage', ['warna_utama' => '#2F5D4B', 'warna_aksen' => '#D9C27A', 'warna_permukaan' => '#EFF4F0', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#1B2B24']],
        ['Rose Anggun', ['warna_utama' => '#A8324F', 'warna_aksen' => '#D99AA8', 'warna_permukaan' => '#FDF1F4', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#3B1020']],
        ['Emas Antik', ['warna_utama' => '#8A5A14', 'warna_aksen' => '#C99A3B', 'warna_permukaan' => '#FBF6EA', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#3A2A0F']],
        ['Midnight', ['warna_utama' => '#E8C46E', 'warna_aksen' => '#E8C46E', 'warna_permukaan' => '#17294A', 'warna_halaman' => '#10203A', 'warna_teks' => '#FFFFFF']],
        ['Burgundy', ['warna_utama' => '#5B1530', 'warna_aksen' => '#C99A3B', 'warna_permukaan' => '#F9EEF1', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#2E0B18']],
    ];

    /** Hex #RRGGBB (huruf besar) atau null. */
    public static function hex(mixed $nilai): ?string
    {
        if (! is_string($nilai)) {
            return null;
        }
        $nilai = trim($nilai);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $nilai) ? strtoupper($nilai) : null;
    }

    /** Ukuran teks dasar dari dokumen; di luar rentang atau bukan angka jatuh ke bawaan. */
    public static function base(mixed $dokumen): int
    {
        $b = is_array($dokumen) ? ($dokumen['global']['base'] ?? null) : null;
        if (! is_int($b) && ! (is_string($b) && ctype_digit($b))) {
            return self::BASE_BAWAAN;
        }
        $b = (int) $b;

        return $b >= self::BASE_MIN && $b <= self::BASE_MAX ? $b : self::BASE_BAWAAN;
    }

    /** Pengali ukuran huruf renderer (1.0 = bawaan). */
    public static function skala(mixed $dokumen): float
    {
        return self::base($dokumen) / self::BASE_BAWAAN;
    }

    /** Dokumen yang aman disimpan. Bawaan (14 px) tidak perlu disimpan: kembali null. */
    public static function bersihkan(mixed $dokumen): ?array
    {
        $base = self::base($dokumen);

        return $base === self::BASE_BAWAAN ? null : ['global' => ['base' => $base]];
    }

    public static function rasioKontras(string $a, string $b): float
    {
        $x = self::luminans($a);
        $y = self::luminans($b);

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    private static function luminans(string $hex): float
    {
        $h = ltrim($hex, '#');
        [$r, $g, $b] = array_map(function ($v) {
            $q = $v / 255;

            return $q <= 0.03928 ? $q / 12.92 : (($q + 0.055) / 1.055) ** 2.4;
        }, [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))]);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Pemeriksaan tema (setara "Periksa tema" di desain). Hanya "err" yang menghalangi penerbitan.
     *
     * @param  array<string,mixed>  $t  kolom warna_* tema (hex valid)
     * @return list<array{lv:string,teks:string}>
     */
    public static function periksa(array $t): array
    {
        $w = [];
        foreach (array_keys(self::WARNA) as $k) {
            $h = self::hex($t[$k] ?? null);
            if ($h === null) {
                return [['lv' => 'err', 'teks' => 'Ada warna yang bukan format #RRGGBB. Perbaiki dulu.']];
            }
            $w[$k] = $h;
        }
        $hasil = [];
        $fmt = fn (float $r) => number_format($r, 1, ',', '');
        $r1 = self::rasioKontras($w['warna_teks'], $w['warna_halaman']);
        $r2 = self::rasioKontras($w['warna_aksen'], $w['warna_halaman']);
        $r3 = self::rasioKontras($w['warna_teks'], $w['warna_permukaan']);
        if ($r1 < 3) {
            $hasil[] = ['lv' => 'err', 'teks' => 'Kontras teks terhadap latar halaman hanya '.$fmt($r1).':1 (minimum 3:1, ideal 4,5:1). Teks sulit dibaca.'];
        } elseif ($r1 < 4.5) {
            $hasil[] = ['lv' => 'warn', 'teks' => 'Kontras teks terhadap latar halaman '.$fmt($r1).':1, di bawah 4,5:1 untuk teks biasa.'];
        }
        if ($r3 < 3) {
            $hasil[] = ['lv' => 'err', 'teks' => 'Kontras teks di atas warna permukaan hanya '.$fmt($r3).':1 (minimum 3:1). Kartu dan bagian berlatar permukaan tidak terbaca.'];
        } elseif ($r3 < 4.5) {
            $hasil[] = ['lv' => 'warn', 'teks' => 'Kontras teks di atas warna permukaan '.$fmt($r3).':1, di bawah 4,5:1.'];
        }
        if ($r2 < 2) {
            $hasil[] = ['lv' => 'warn', 'teks' => 'Warna aksen nyaris menyatu dengan latar halaman ('.$fmt($r2).':1). Ornamen dan judul beraksen akan hilang.'];
        }
        if ($hasil === []) {
            $hasil[] = ['lv' => 'ok', 'teks' => 'Kontras teks '.$fmt($r1).':1 pada halaman dan '.$fmt($r3).':1 pada permukaan. Tema siap diterbitkan.'];
        }

        return $hasil;
    }

    /** @param  list<array{lv:string,teks:string}>  $hasil */
    public static function adaGalat(array $hasil): bool
    {
        foreach ($hasil as $h) {
            if ($h['lv'] === 'err') {
                return true;
            }
        }

        return false;
    }
}
