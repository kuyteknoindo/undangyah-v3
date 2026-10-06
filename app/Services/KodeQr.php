<?php

namespace App\Services;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Pembuat kode QR SVG (tanpa imagick/gd), dipakai Bagikan, RSVP & Tamu, dan dialog terbit. */
class KodeQr
{
    public static function svg(string $data, int $ukuran = 240, string $warna = '#111827', string $latar = '#FFFFFF'): string
    {
        $hex = fn (string $h): Rgb => new Rgb(...sscanf(ltrim($h, '#'), '%02x%02x%02x'));
        $gaya = new RendererStyle($ukuran, 1, null, null, Fill::uniformColor($hex($latar), $hex($warna)));
        $svg = (new Writer(new ImageRenderer($gaya, new SvgImageBackEnd)))->writeString($data);

        return trim(preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }

    public static function dataUri(string $data, int $ukuran = 240): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($data, $ukuran));
    }
}
