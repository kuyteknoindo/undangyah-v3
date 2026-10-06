<?php

namespace Tests\Feature;

use App\Models\Undangan;
use App\Services\SlugUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugUndanganTest extends TestCase
{
    use RefreshDatabase;

    private function layanan(): SlugUndangan
    {
        return app(SlugUndangan::class);
    }

    public function test_bersihkan_mengubah_teks_menjadi_slug(): void
    {
        $this->assertSame('raka-sinta', $this->layanan()->bersihkan('  Raka & Sinta  '));
        $this->assertSame('pesta-nara-ke-7', $this->layanan()->bersihkan('Pesta Nara ke-7!!'));
        $this->assertSame('cafe-bunga', $this->layanan()->bersihkan('Café Bunga'));
        $this->assertSame(60, strlen($this->layanan()->bersihkan(str_repeat('a', 100))));
    }

    public function test_format_slug_divalidasi(): void
    {
        $l = $this->layanan();
        $this->assertNull($l->galatFormat('raka-sinta'));
        foreach (['ab', str_repeat('a', 61), 'Huruf-Besar', 'dua--strip', '-awal', 'akhir-', 'ada spasi', 'garis_bawah', 'titik.titik'] as $buruk) {
            $this->assertNotNull($l->galatFormat($buruk), "'{$buruk}' seharusnya ditolak");
        }
    }

    public function test_slug_terlarang_dan_bentrok_dengan_rute_ditolak(): void
    {
        $l = $this->layanan();
        foreach (['admin', 'login', 'masuk', 'api', 'livewire', 'beranda', 'storage', 'robots-txt', 'undangyah'] as $kata) {
            $this->assertNotNull($l->galatFormat($kata), "'{$kata}' seharusnya terlarang");
        }
        // segmen pertama rute terdaftar juga terlarang walau tak ada di daftar tetap
        $this->assertTrue($l->terlarang('verifikasi-email'));
    }

    public function test_ketersediaan_memperhitungkan_undangan_sendiri(): void
    {
        $u = Undangan::factory()->create(['slug' => 'raka-sinta']);
        $l = $this->layanan();

        $this->assertNotNull($l->galat('raka-sinta'));
        $this->assertNull($l->galat('raka-sinta', $u->id));
        $this->assertNull($l->galat('sinta-raka'));
    }

    public function test_buat_dari_judul_menambah_akhiran_bila_bentrok(): void
    {
        Undangan::factory()->create(['slug' => 'raka-sinta']);
        Undangan::factory()->create(['slug' => 'raka-sinta-2']);
        $l = $this->layanan();

        $this->assertSame('raka-sinta-3', $l->buat('Raka & Sinta'));
        $this->assertSame('pesta-ulang-tahun', $l->buat('Pesta Ulang Tahun'));
        $this->assertSame('admin-2', $l->buat('Admin')); // kata terlarang diberi akhiran angka
        $this->assertGreaterThanOrEqual(3, strlen($l->buat('!!')));
        $this->assertNull($l->galat($l->buat('Login')));
    }
}
