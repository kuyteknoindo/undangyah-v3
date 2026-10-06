<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Pengaturan;

/**
 * Pengaturan Storage (admin): kuota per pengguna, batas ukuran per file, tipe yang diizinkan.
 * Disimpan di `Pengaturan` kunci `storage`; nilai di luar rentang jatuh ke bawaan agar data rusak tidak membuka batas.
 */
class KonfigStorage
{
    public const KUNCI = 'storage';

    public const KUOTA_MIN = 1;

    public const KUOTA_MAKS = 100000;

    public const MAKS_MIN = 0.1;

    /** Batas atas per file = batas bawaan unggahan sementara Livewire (12 MB); PHP-FPM sendiri 20 MB. */
    public const MAKS_MAKS = 12;

    public const BAWAAN = [
        'kuota_mb' => 100,
        'maks_mb' => 10,
        'tipe' => [Media::GAMBAR => true, Media::AUDIO => true, Media::VIDEO => false, Media::DOKUMEN => true],
    ];

    /** @return array{kuota_mb:int,maks_mb:float,tipe:array<string,bool>,kuota_byte:int,maks_byte:int} */
    public static function ambil(): array
    {
        $nilai = Pengaturan::ambil(self::KUNCI, []);
        $nilai = is_array($nilai) ? $nilai : [];

        $kuota = is_numeric($nilai['kuota_mb'] ?? null) ? (int) $nilai['kuota_mb'] : self::BAWAAN['kuota_mb'];
        if ($kuota < self::KUOTA_MIN || $kuota > self::KUOTA_MAKS) {
            $kuota = self::BAWAAN['kuota_mb'];
        }
        $maks = is_numeric($nilai['maks_mb'] ?? null) ? (float) $nilai['maks_mb'] : (float) self::BAWAAN['maks_mb'];
        if ($maks < self::MAKS_MIN || $maks > self::MAKS_MAKS) {
            $maks = (float) self::BAWAAN['maks_mb'];
        }
        $tipe = [];
        foreach (self::BAWAAN['tipe'] as $k => $bawaan) {
            $tipe[$k] = is_array($nilai['tipe'] ?? null) && array_key_exists($k, $nilai['tipe']) ? (bool) $nilai['tipe'][$k] : $bawaan;
        }

        return [
            'kuota_mb' => $kuota,
            'maks_mb' => $maks,
            'tipe' => $tipe,
            'kuota_byte' => $kuota * 1048576,
            'maks_byte' => (int) round($maks * 1048576),
        ];
    }

    /** @param  array{kuota_mb:int,maks_mb:float|int,tipe:array<string,bool>}  $nilai */
    public static function simpan(array $nilai): void
    {
        Pengaturan::simpan(self::KUNCI, [
            'kuota_mb' => (int) $nilai['kuota_mb'],
            'maks_mb' => (float) $nilai['maks_mb'],
            'tipe' => array_map('boolval', $nilai['tipe']),
        ]);
    }

    public static function adaBawaan(): bool
    {
        return Pengaturan::ambil(self::KUNCI) === null;
    }
}
