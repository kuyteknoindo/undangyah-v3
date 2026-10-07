<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Situs dev kini terbuka tanpa Basic Auth: pastikan rute admin tertutup, berkas sensitif tidak terlayani,
 * dan halaman galat tidak membocorkan isi .env atau jejak tumpukan.
 */
class KeamananDevTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> URI GET milik rute admin.* (parameter diganti 1). */
    private function uriAdmin(): array
    {
        $uri = [];
        foreach (Route::getRoutes()->getRoutes() as $rute) {
            if (! str_starts_with((string) $rute->getName(), 'admin.') || ! in_array('GET', $rute->methods(), true)) {
                continue;
            }
            $uri[] = '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $rute->uri()), '/');
        }

        return array_values(array_unique($uri));
    }

    public function test_ada_rute_admin_untuk_disapu(): void
    {
        $this->assertGreaterThanOrEqual(14, count($this->uriAdmin()));
    }

    public function test_semua_rute_admin_mengalihkan_tamu_ke_masuk(): void
    {
        foreach ($this->uriAdmin() as $uri) {
            $this->get($uri)->assertRedirect(route('masuk'));
        }
    }

    public function test_semua_rute_admin_403_bagi_pengguna_biasa(): void
    {
        $user = User::factory()->create();

        foreach ($this->uriAdmin() as $uri) {
            $kode = $this->actingAs($user)->get($uri)->getStatusCode();
            $this->assertSame(403, $kode, "{$uri} memberi {$kode}");
        }
    }

    public function test_berkas_sensitif_tidak_terlayani_dan_tidak_membocorkan_isi(): void
    {
        $jalur = [
            '.env', '.env.example', '.git/config', '.git/HEAD', 'storage/logs/laravel.log', 'storage/app/backups',
            'composer.json', 'composer.lock', 'artisan', 'phpunit.xml', 'PROGRESS.md', 'CLAUDE.md',
            'database/database.sqlite', 'vendor/autoload.php', 'ADMIN-LOGIN.txt', 'BASIC-AUTH.txt',
            '../ADMIN-LOGIN.txt', '../BASIC-AUTH.txt', 'config/app.php', 'bootstrap/app.php',
        ];

        foreach ($jalur as $j) {
            $r = $this->get('/'.$j);
            $this->assertContains($r->getStatusCode(), [301, 302, 403, 404], "Jalur /{$j} memberi {$r->getStatusCode()}");
            $isi = (string) $r->getContent();
            foreach (['APP_KEY=', 'DB_PASSWORD', 'base64:', 'ADMIN-LOGIN', 'laravel.log'] as $rahasia) {
                $this->assertStringNotContainsString($rahasia, $isi, "Jalur /{$j} membocorkan {$rahasia}");
            }
        }
    }

    public function test_halaman_galat_tidak_membocorkan_jejak_tumpukan_atau_env(): void
    {
        config(['app.debug' => false]);

        $isi = (string) $this->get('/halaman-yang-tidak-ada/sama-sekali')->assertNotFound()->getContent();

        foreach (['Stack trace', 'vendor/laravel', 'APP_KEY', 'DB_PASSWORD', '/home/deploy', 'Whoops'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $isi);
        }
    }

    public function test_folder_public_tidak_memuat_berkas_berbahaya(): void
    {
        $terlarang = ['env', 'sql', 'log', 'bak', 'old', 'orig', 'swp', 'zip', 'tar', 'gz', 'sqlite', 'php~'];
        $ditemukan = [];

        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $berkas) {
            $jalur = str_replace(public_path(), '', $berkas->getPathname());
            // symlink storage mengarah ke media unggahan pengguna (divalidasi PenyimpanMedia): bukan bagian tinjauan ini
            if (str_starts_with($jalur, '/storage')) {
                continue;
            }
            $nama = strtolower($berkas->getFilename());
            if (in_array(strtolower($berkas->getExtension()), $terlarang, true) || in_array($nama, ['.env', 'phpinfo.php', 'info.php', 'hot', 'adminer.php'], true)) {
                $ditemukan[] = $jalur;
            }
        }

        $this->assertSame([], $ditemukan, 'Berkas berbahaya di public/: '.implode(', ', $ditemukan));
    }

    public function test_index_php_tidak_menampilkan_phpinfo_atau_direktori(): void
    {
        $this->get('/index.php/.env')->assertNotFound();
        $this->get('/storage/')->assertStatus(404);
    }
}
