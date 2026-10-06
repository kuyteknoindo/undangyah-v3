<?php

namespace Tests\Feature;

use App\Mail\KodeOtpMail;
use App\Mail\KonfirmasiEmailBaruMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LogoBrandTest extends TestCase
{
    use RefreshDatabase;

    private const ALT = 'alt="undangyah.com, undangan digital"';

    private function pastikanLogo(string $html): void
    {
        $this->assertStringContainsString('brand/undangyah-logo-full.png', $html);
        $this->assertStringContainsString(self::ALT, $html);
        $this->assertMatchesRegularExpression('#<img[^>]+undangyah-logo-full\.png[^>]+width="\d+"[^>]+height="\d+"#', $html);
    }

    private function pastikanIkonSitus(string $html): void
    {
        $this->assertStringContainsString('rel="icon"', $html);
        $this->assertStringContainsString('favicon.ico', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#1D4ED8">', $html);
    }

    public function test_aset_brand_ada_dengan_dimensi_benar(): void
    {
        $harapan = [
            'brand/undangyah-logo-full.png' => [409, 95],
            'brand/undangyah-logo-ikon.png' => [160, 160],
            'brand/undangyah-logo-ikon-kecil.png' => [80, 80],
            'brand/favicon-32.png' => [32, 32],
            'brand/apple-touch-icon.png' => [180, 180],
            'brand/icon-192.png' => [192, 192],
            'brand/og-undangyah.png' => [1200, 630],
        ];
        foreach ($harapan as $berkas => [$w, $h]) {
            $this->assertFileExists(public_path($berkas));
            $info = getimagesize(public_path($berkas));
            $this->assertSame([$w, $h], [$info[0], $info[1]], $berkas);
            $this->assertSame('image/png', $info['mime'], $berkas);
        }
    }

    public function test_favicon_ico_bukan_nol_byte_dan_berisi_16_32_48(): void
    {
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));
        $kepala = unpack('vreserved/vtipe/vjumlah', file_get_contents(public_path('favicon.ico'), false, null, 0, 6));
        $this->assertSame(1, $kepala['tipe']);
        $this->assertSame(3, $kepala['jumlah']);
    }

    public function test_halaman_auth_memuat_logo_tautan_beranda_dan_meta(): void
    {
        foreach (['/masuk', '/daftar', '/lupa-sandi'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->pastikanLogo($html);
            $this->assertStringContainsString('<a href="'.route('beranda').'" title="Ke beranda"', $html, $url);
            $this->pastikanIkonSitus($html);
            $this->assertStringContainsString('<meta property="og:image" content="'.url('/brand/og-undangyah.png').'">', $html, $url);
            $this->assertStringContainsString('<meta property="og:title"', $html);
            $this->assertStringContainsString('<meta name="description"', $html);
        }
    }

    public function test_halaman_verifikasi_email_memuat_logo(): void
    {
        $user = User::factory()->unverified()->create();
        $html = $this->actingAs($user)->get('/verifikasi-email')->assertOk()->getContent();
        $this->pastikanLogo($html);
        $this->pastikanIkonSitus($html);
    }

    public function test_sidebar_aplikasi_memuat_logo_yang_tertaut_ke_beranda(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/beranda')->assertOk()->getContent();
        $this->pastikanLogo($html);
        $this->pastikanIkonSitus($html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('beranda'), '#').'"[^>]*>\s*<img[^>]+undangyah-logo-full\.png#', $html);
        $this->assertMatchesRegularExpression('#<img[^>]+width="164"[^>]+height="38"#', $html);
        $this->assertStringNotContainsString('>undangyah</span>', $html);
    }

    public function test_halaman_galat_dan_pemeliharaan_memuat_logo(): void
    {
        $html = $this->get('/tidak-ada-halaman-ini')->assertNotFound()->getContent();
        $this->pastikanLogo($html);
        $this->pastikanIkonSitus($html);

        Route::get('/_galat/{kode}', fn (int $kode) => abort($kode))->middleware('web');
        $this->pastikanLogo($this->get('/_galat/500')->assertStatus(500)->getContent());

        $this->artisan('undangyah:pemeliharaan', ['status' => 'on'])->assertSuccessful();
        $this->pastikanLogo($this->actingAs(User::factory()->create())->get('/beranda')->assertStatus(503)->getContent());
        $this->artisan('undangyah:pemeliharaan', ['status' => 'off'])->assertSuccessful();
    }

    public function test_halaman_offline_dan_dokumen_memuat_logo_dan_og(): void
    {
        $this->pastikanLogo($this->get('/offline')->assertOk()->getContent());

        foreach (['/syarat', '/privasi'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->pastikanLogo($html);
            $this->pastikanIkonSitus($html);
            $this->assertStringContainsString('<meta property="og:image" content="'.url('/brand/og-undangyah.png').'">', $html, $url);
        }
    }

    public function test_email_memuat_url_logo_absolut_dengan_alt_dan_lebar(): void
    {
        config(['app.url' => 'https://dev.undangyah.com']);
        $htmlOtp = (new KodeOtpMail('123456', 'Deni', 'verifikasi', 10))->render();
        $htmlKonfirmasi = (new KonfirmasiEmailBaruMail('Deni', 'baru@contoh.com', 'https://dev.undangyah.com/x', 30))->render();

        foreach ([$htmlOtp, $htmlKonfirmasi] as $html) {
            $this->assertStringContainsString('src="https://dev.undangyah.com/brand/undangyah-logo-full.png"', $html);
            $this->assertStringContainsString(self::ALT, $html);
            $this->assertStringContainsString('width="164"', $html);
        }
    }
}
