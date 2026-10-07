<?php

namespace App\Support\Editor;

/**
 * Pustaka foto bawaan sistem untuk Pemilih Gambar (setara SAMPLES di papan). Berkas SVG lokal di public/editor/pustaka
 * (tanpa berkas eksternal). Id ("i1".."i9") dan path berasal dari tabel ini saja: klien hanya mengirim id, jadi `src`
 * yang disimpan di undangan tidak pernah berasal dari string klien.
 */
final class PustakaBawaan
{
    /** @var array<string,array{nama:string,kat:string,color:string}> */
    public const ITEM = [
        'i1' => ['nama' => 'prewedding-01.jpg', 'kat' => 'Orang', 'color' => '#C9D8F5'],
        'i2' => ['nama' => 'prewedding-02.jpg', 'kat' => 'Orang', 'color' => '#D7E3FA'],
        'i3' => ['nama' => 'portrait-raka.jpg', 'kat' => 'Orang', 'color' => '#BBD0F3'],
        'i4' => ['nama' => 'portrait-sinta.jpg', 'kat' => 'Orang', 'color' => '#CFDDF6'],
        'i5' => ['nama' => 'ornamen-emas.png', 'kat' => 'Asset', 'color' => '#FFF4CC'],
        'i6' => ['nama' => 'pola-bunga.png', 'kat' => 'Asset', 'color' => '#F8E1DE'],
        'i7' => ['nama' => 'gedung-resepsi.jpg', 'kat' => 'Lainnya', 'color' => '#D7FFB8'],
        'i8' => ['nama' => 'cincin.jpg', 'kat' => 'Lainnya', 'color' => '#ECEEF1'],
        'i9' => ['nama' => 'undangan-cover.jpg', 'kat' => 'Lainnya', 'color' => '#E3ECFD'],
    ];

    public static function ada(string $id): bool
    {
        return isset(self::ITEM[$id]);
    }

    public static function url(string $id): ?string
    {
        return isset(self::ITEM[$id]) ? '/editor/pustaka/'.$id.'.svg' : null;
    }

    /** @return array{nama:string,kat:string,color:string,src:string}|null */
    public static function info(string $id): ?array
    {
        return isset(self::ITEM[$id]) ? self::ITEM[$id] + ['src' => self::url($id)] : null;
    }

    /** Id sampel dari path `src` tersimpan, atau null. */
    public static function idDariUrl(string $src): ?string
    {
        return preg_match('~^/editor/pustaka/(i[1-9])\.svg$~', $src, $m) ? $m[1] : null;
    }
}
