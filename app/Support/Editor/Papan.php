<?php

namespace App\Support\Editor;

use App\Models\Tema;
use App\Support\SkemaUndangan;

/**
 * Konstanta dan pembantu murni untuk kanvas editor (setara prelude papan EditUndanganDesktop: mkTheme, SWATCHES,
 * CROP_RATIOS, imgSty, fmtDate, gcalUrl, dst.). Tidak menyentuh database dan tidak mengubah state.
 */
final class Papan
{
    public const SERIF = "ui-serif,'New York',Georgia,serif";

    public const SANS = "-apple-system,BlinkMacSystemFont,'SF Pro Text','SF Pro Display','Inter',system-ui,sans-serif";

    public const SKRIP = "'Snell Roundhand','Apple Chancery','Brush Script MT','Segoe Script',cursive";

    public const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public const PLAT_LS = [['youtube', 'YouTube Live'], ['zoom', 'Zoom'], ['meet', 'Google Meet'], ['instagram', 'Instagram Live'], ['tiktok', 'TikTok Live']];

    public const PLAT_SOC = [['instagram', 'Instagram'], ['tiktok', 'TikTok'], ['facebook', 'Facebook'], ['youtube', 'YouTube'], ['x', 'X (Twitter)']];

    public const KATEGORI = ['Semua', 'Asset', 'Orang', 'Lainnya', 'Unggahan'];

    /** [kode, rasio]; rasio 0 = ikut foto asli. */
    public const RASIO_CROP = [['1:1', 1.0], ['4:5', 0.8], ['3:4', 0.75], ['16:9', 16 / 9], ['asli', 0.0]];

    public const CROP_AWAL = ['ratio' => '1:1', 'zoom' => 100, 'x' => 50, 'y' => 50];

    public const TATA_LETAK = [['grid', 'Kotak'], ['masonry', 'Mosaik'], ['slider', 'Geser']];

    public const MASONRY = [0.8, 1, 1.25, 1, 1.25, 0.8];

    /** Pilihan warna teks panel Properti; 'ACC' = warna aksen tema. */
    public const SWATCH = [
        ['#262A31', 'Hitam'], ['#FFFFFF', 'Putih'], ['ACC', 'Warna aksen'], ['#555B66', 'Abu-abu'], ['#C8A24A', 'Emas'], ['#FFF4CC', 'Krem'],
    ];

    public const TIER_GAYA = [
        'Gratis' => 'background:#D7FFB8;color:#3C7A00;',
        'Premium' => 'background:#FFF4CC;color:#B25E00;',
        'Deluxe' => 'background:#262A31;color:#FFFFFF;',
    ];

    public const IKON_BAGIAN = [
        'sampul' => 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z M12 18h.01',
        'quotes' => 'M3 21c3 0 7-1 7-8V5c0-1.25-.76-2-2-2H4c-1.25 0-2 .75-2 1.97V11c0 1.25.75 2 2 2h4 M15 21c3 0 7-1 7-8V5c0-1.25-.76-2-2-2h-4c-1.25 0-2 .75-2 1.97V11c0 1.25.75 2 2 2h4',
        'mempelai' => 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z',
        'acara' => 'M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z M16 2v4 M8 2v4 M3 10h18',
        'lovestory' => 'M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20',
        'foto' => 'M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'gallery' => 'M18 22H4a2 2 0 0 1-2-2V6 M22 2H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z M10 8a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z M22 13l-3.09-3.09a2 2 0 0 0-2.82 0L8 18',
        'musik' => 'M9 18V5l12-2v13 M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0z M21 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
        'rundown' => 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01',
        'turut' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8z M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
        'kado' => 'M20 12v10H4V12 M2 7h20v5H2z M12 22V7 M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z',
        'livestream' => 'M16 13l5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5 M4 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z',
        'gcal' => 'M8 2v4 M16 2v4 M21 13V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8 M3 10h18 M16 19h6 M19 16v6',
        'social' => 'M18 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6z M6 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z M18 13a3 3 0 1 0 0 6 3 3 0 0 0 0-6z M8.6 13.5l6.8 4 M15.4 6.5l-6.8 4',
        'dresscode' => 'M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z',
        'rsvp' => 'M22 11.08V12a10 10 0 1 1-5.93-9.14 M22 4 12 14.01l-3-3',
        'tamu' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h3v3h-3z M20 14v3 M14 20h3 M20 20h1',
        'penutup' => 'M20 6 9 17l-5-5',
    ];

    // ---------------------------------------------------------------- warna (mkTheme)

    public static function hx(?string $c): string
    {
        return preg_match('/^#?([0-9a-f]{6})$/i', (string) $c, $m) ? '#'.strtoupper($m[1]) : '#000000';
    }

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $c): array
    {
        $h = substr(self::hx($c), 1);

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    public static function luminans(string $c): float
    {
        $r = array_map(function ($v) {
            $q = $v / 255;

            return $q <= 0.03928 ? $q / 12.92 : (($q + 0.055) / 1.055) ** 2.4;
        }, self::rgb($c));

        return 0.2126 * $r[0] + 0.7152 * $r[1] + 0.0722 * $r[2];
    }

    public static function kontras(string $c, string $gelap = '#10203A', string $terang = '#FFFFFF'): string
    {
        return self::luminans($c) > 0.42 ? $gelap : $terang;
    }

    public static function campur(string $a, string $b, float $t): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);

        return '#'.strtoupper(implode('', array_map(fn ($i) => str_pad(dechex((int) round($x[$i] + ($y[$i] - $x[$i]) * $t)), 2, '0', STR_PAD_LEFT), [0, 1, 2])));
    }

    /**
     * Token warna kanvas dari tema (setara mkTheme di papan). Tema kosong memakai nilai bawaan papan (Biru Klasik).
     *
     * @return array<string,string|bool>
     */
    public static function tokenTema(?Tema $t): array
    {
        $utama = self::hx($t?->warna_utama ?: '#1D4ED8');
        $aksen = self::hx($t?->warna_aksen ?: '#E8C46E');
        $permukaan = self::hx($t?->warna_permukaan ?: '#F3F6FB');
        $halaman = self::hx($t?->warna_halaman ?: '#FFFFFF');
        $teks = self::hx($t?->warna_teks ?: '#10203A');

        $acc = abs(self::luminans($utama) - self::luminans($halaman)) > 0.3 ? $utama : $aksen;
        $terangSampul = self::luminans($utama) > 0.6;

        return [
            'acc' => $acc, 'dep' => self::campur($acc, '#000000', 0.25), 'tint' => self::campur($acc, $halaman, 0.88),
            'bgB' => $permukaan, 'page' => $halaman, 'ink' => $teks, 'mut' => self::campur($teks, $halaman, 0.38),
            'cov' => $utama, 'onA' => self::kontras($utama), 'onAcc' => self::kontras($acc), 'coverDark' => ! $terangSampul,
            'btn' => $terangSampul ? $teks : '#FFFFFF', 'btnTx' => $terangSampul ? '#FFFFFF' : $utama,
            'hf' => match ($t?->font_judul) {
                'jakarta' => self::SANS,
                'script' => self::SKRIP,
                default => self::SERIF,
            },
        ];
    }

    // ---------------------------------------------------------------- meta bidang untuk gaya kanvas

    private const DASAR = ['fs' => 14, 'w' => 500, 'ta' => 'left', 'c' => 'ink', 'ff' => 'sans', 'lh' => 1.35, 'rows' => 0, 'ls' => '0', 'up' => false, 'ex' => '', 'kind' => 'text', 'canvas' => true, 'opt' => false, 'hint' => '', 'opts' => null];

    /** @var array<string,?array> */
    private static array $cacheMeta = [];

    /**
     * Meta lengkap sebuah kunci (bidang tetap atau "{tpl}{id}_{kunci}") dengan nilai bawaan gaya, atau null bila tidak dikenal.
     * Tidak memeriksa apakah id item ada di daftar (itu urusan IsiUndangan::meta).
     */
    public static function meta(string $k): ?array
    {
        if (array_key_exists($k, self::$cacheMeta)) {
            return self::$cacheMeta[$k];
        }
        $hasil = null;
        $bidang = SkemaUndangan::bidang();
        if (isset($bidang[$k])) {
            $hasil = array_merge(self::DASAR, $bidang[$k]);
        } elseif (preg_match('/^([a-z]{2})(\d+)_(\w+)$/', $k, $m) && isset(SkemaUndangan::templat()[$m[1]])) {
            foreach (SkemaUndangan::templat()[$m[1]]['f'] as $f) {
                if ($f[0] === $m[3]) {
                    $hasil = array_merge(self::DASAR, ['g' => SkemaUndangan::templat()[$m[1]]['g'], 'label' => $f[1], 'v' => (string) $f[2]], $f[3] ?? []);
                }
            }
        }

        return self::$cacheMeta[$k] = $hasil;
    }

    // ---------------------------------------------------------------- bantu tampilan

    public static function fmtTanggal(string $s): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return '';
        }
        $t = mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);

        return self::HARI[(int) date('w', $t)].', '.((int) $m[3]).' '.self::BULAN[(int) $m[2] - 1].' '.$m[1];
    }

    public static function fmtJam(string $t): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $t) ? str_replace(':', '.', $t) : '';
    }

    public static function gcalUrl(array $d): string
    {
        $ds = str_replace('-', '', (string) ($d['gcTgl'] ?? ''));
        $a = str_replace(':', '', (string) ($d['gcMulai'] ?? ''));
        $b = str_replace(':', '', (string) ($d['gcSelesai'] ?? ''));
        if ($ds === '' || $a === '' || $b === '') {
            return '';
        }

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.rawurlencode((string) ($d['gcJudul'] ?? '')).'&dates='.$ds.'T'.$a.'00/'.$ds.'T'.$b.'00&ctz=Asia%2FJakarta&location='.rawurlencode((string) ($d['gcLokasi'] ?? '')).'&details='.rawurlencode((string) ($d['gcDesk'] ?? ''));
    }

    public static function isUrl(?string $v): bool
    {
        return (bool) preg_match('~^https?://[^\s]+\.[^\s]+$~i', trim((string) $v));
    }

    public static function urlPendek(?string $v): string
    {
        $x = rtrim((string) preg_replace('~^https?://~i', '', trim((string) $v)), '/');

        return mb_strlen($x) > 30 ? mb_substr($x, 0, 29).'…' : $x;
    }

    public static function kunciRasio(?string $kode): ?float
    {
        foreach (self::RASIO_CROP as [$k, $r]) {
            if ($k === $kode) {
                return $r > 0 ? $r : null;
            }
        }

        return null;
    }

    /** Gaya gambar terpotong (setara imgSty papan). */
    public static function gayaGambar(?array $c): string
    {
        $k = $c ?? [];
        $z = max(100, min(300, (int) ($k['zoom'] ?? 100))) / 100;
        $x = max(0, min(100, (int) ($k['x'] ?? 50)));
        $y = max(0, min(100, (int) ($k['y'] ?? 50)));

        return 'position:absolute;top:0;left:0;width:100%;height:100%;display:block;object-fit:'.(($k['ratio'] ?? '') === 'asli' ? 'contain' : 'cover').';object-position:'.$x.'% '.$y.'%;transform:scale('.rtrim(rtrim(number_format($z, 2, '.', ''), '0'), '.').');transform-origin:'.$x.'% '.$y.'%;';
    }

    /** Normalkan crop dari penyimpanan lama/klien; null bila sama dengan bawaan. Rasio lama "bingkai" = 1:1. */
    public static function normalCrop(mixed $c): ?array
    {
        if (! is_array($c)) {
            return null;
        }
        $ratio = (string) ($c['ratio'] ?? '1:1');
        if ($ratio === 'bingkai') {
            $ratio = '1:1';
        }
        if (! in_array($ratio, array_column(self::RASIO_CROP, 0), true)) {
            return null;
        }
        $hasil = [
            'ratio' => $ratio,
            'zoom' => (int) max(100, min(300, round((float) ($c['zoom'] ?? 100)))),
            'x' => (int) max(0, min(100, round((float) ($c['x'] ?? 50)))),
            'y' => (int) max(0, min(100, round((float) ($c['y'] ?? 50)))),
        ];

        return $hasil === self::CROP_AWAL ? null : $hasil;
    }

    /** Warna "sampel" bawaan tidak punya tanda; potong teks URL aman untuk atribut. */
    public static function jsArg(mixed $a): string
    {
        if (is_int($a) || is_float($a)) {
            return (string) $a;
        }
        if (is_bool($a)) {
            return $a ? 'true' : 'false';
        }

        return "'".str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", ' ', ' '], (string) $a)."'";
    }
}
