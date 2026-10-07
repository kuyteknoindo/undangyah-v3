<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Format teks yang dipakai papan Billing/Checkout/Konfirmasi (disalin dari helper papan: udRp, udDate, fmtDT, leftText, stInfo). */
class FormatPapan
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Status pesanan => [label, gaya chip] persis ST di papan. */
    private const STATUS = [
        'lunas' => ['Lunas', 'background:#D7FFB8;color:#3C7A00'],
        'menunggu' => ['Menunggu', 'background:#FFF4CC;color:#9A6400'],
        'kedaluwarsa' => ['Kedaluwarsa', 'background:#ECEEF1;color:#454B55'],
        'batal' => ['Dibatalkan', 'background:#FFE0E0;color:#EA2B2B'],
        'refund' => ['Dikembalikan', 'background:#ECEEF1;color:#454B55'],
    ];

    public static function rp(int|float|null $n): string
    {
        return 'Rp '.number_format((int) round((float) $n), 0, ',', '.');
    }

    /** 14 Mar 2027 */
    public static function tgl(?CarbonInterface $t): string
    {
        return $t ? $t->day.' '.self::BULAN[$t->month - 1].' '.$t->year : '';
    }

    /** 14 Mar 2027, 10.05 */
    public static function tglJam(?CarbonInterface $t): string
    {
        return $t ? self::tgl($t).', '.$t->format('H.i') : '';
    }

    /** "2 jam 5 mnt lagi" / "12 mnt lagi" / "waktu habis" */
    public static function sisaWaktu(?CarbonInterface $batas): string
    {
        $detik = $batas ? now()->diffInSeconds($batas, false) : 0;
        if ($detik <= 0) {
            return 'waktu habis';
        }
        $jam = intdiv((int) $detik, 3600);
        $menit = intdiv((int) $detik % 3600, 60);

        return $jam >= 1 ? $jam.' jam '.$menit.' mnt lagi' : max(1, $menit).' mnt lagi';
    }

    /** @return array{0:string,1:string} [label, gaya chip] */
    public static function status(string $status): array
    {
        return self::STATUS[$status] ?? [$status === '' ? '-' : $status, 'background:#ECEEF1;color:#454B55'];
    }

    /** Gaya kolom isian papan (inSty). */
    public static function gayaInput(bool $salah = false): string
    {
        return 'box-sizing:border-box;width:100%;min-height:48px;padding:0 14px;border:2px solid '.($salah ? '#EA2B2B' : '#E2E5E9').';border-radius:16px;background:#F4F5F7;font-size:15px;line-height:20px;font-weight:500';
    }
}
