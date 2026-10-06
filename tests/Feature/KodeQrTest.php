<?php

namespace Tests\Feature;

use App\Services\KodeQr;
use Tests\TestCase;

class KodeQrTest extends TestCase
{
    public function test_menghasilkan_svg_valid(): void
    {
        $svg = KodeQr::svg('https://undangyah.com/contoh');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_data_uri_berupa_svg_base64(): void
    {
        $uri = KodeQr::dataUri('abc');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertStringContainsString('<svg', base64_decode(substr($uri, 26)));
    }

    public function test_data_berbeda_menghasilkan_kode_berbeda(): void
    {
        $this->assertNotSame(KodeQr::svg('satu'), KodeQr::svg('dua'));
    }
}
