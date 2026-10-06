<?php

namespace App\Support;

/** Pilihan warna latar avatar inisial (mengikuti UA_AVC di desain Edit Profil). */
class WarnaAvatar
{
    public const DAFTAR = [
        'biru' => ['Biru', '#1D4ED8'],
        'hijau' => ['Hijau', '#2F5D4B'],
        'emas' => ['Emas', '#9A6400'],
        'rose' => ['Rose', '#A8324F'],
        'malam' => ['Malam', '#262A31'],
        'ungu' => ['Ungu', '#5B3FA8'],
        'teal' => ['Teal', '#0F766E'],
        'jingga' => ['Jingga', '#B4491E'],
    ];

    public static function kunci(): array
    {
        return array_keys(self::DAFTAR);
    }

    public static function hex(?string $kunci): string
    {
        return (self::DAFTAR[$kunci] ?? self::DAFTAR['biru'])[1];
    }
}
