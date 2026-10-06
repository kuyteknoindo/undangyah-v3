<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\User;
use App\Services\SlugUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyaratPrivasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_terbuka_untuk_tamu_tanpa_login(): void
    {
        $this->get('/syarat')->assertOk()
            ->assertSee('Syarat &amp; Ketentuan', false)->assertSee('Penerimaan syarat')->assertSee('Terakhir diperbarui 1 Oktober 2026')
            ->assertSee('Teks contoh untuk prototipe')->assertSee('Masuk')->assertDontSee('Panel Admin')
            ->assertSee('noindex', false);

        $this->get('/privasi')->assertOk()->assertSee('Kebijakan Privasi')->assertSee('Data yang kami kumpulkan')->assertSee('Hak kamu')
            ->assertDontSee('Penerimaan syarat');
    }

    public function test_pengguna_terverifikasi_melihatnya_di_dalam_aplikasi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/syarat')->assertOk()->assertSee('Buat Undangan')
            ->assertSee('Hubungi layanan pelanggan');
        $this->actingAs($user)->get('/privasi')->assertOk()->assertSee('Cara kami memakai data');
    }

    public function test_pengguna_belum_terverifikasi_memakai_kerangka_publik(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/syarat')->assertOk()
            ->assertDontSee('Buat Undangan')->assertDontSee('Hubungi layanan pelanggan');
    }

    public function test_nama_rute_dipakai_profil_dan_checkout(): void
    {
        $this->assertSame(url('/syarat'), route('syarat'));
        $this->assertSame(url('/privasi'), route('privasi'));

        $this->actingAs(User::factory()->create())->get('/akun')->assertOk()->assertSee(route('syarat'), false);
    }

    public function test_slug_terlarang_dan_tidak_bentrok_dengan_undangan_publik(): void
    {
        foreach (['syarat', 'privasi', 'bantuan', 'hubungi'] as $kata) {
            $this->assertContains($kata, SlugUndangan::TERLARANG);
            $this->assertNotNull(app(SlugUndangan::class)->galatFormat($kata));
        }
    }

    public function test_pemilik_dapat_mengganti_isi_dan_mematikan_catatan_contoh(): void
    {
        Pengaturan::simpan('legal', [
            'diperbarui' => '2 November 2026',
            'contoh' => false,
            'syarat' => [['judul' => 'Ketentuan resmi', 'isi' => "Isi resmi **penting**\n\n- butir <b>a</b>"]],
        ]);

        $this->get('/syarat')->assertOk()
            ->assertSee('Ketentuan resmi')->assertSee('<strong>penting</strong>', false)->assertSee('&lt;b&gt;a&lt;/b&gt;', false)
            ->assertSee('Terakhir diperbarui 2 November 2026')
            ->assertDontSee('Teks contoh untuk prototipe')->assertDontSee('Penerimaan syarat')
            ->assertSee('index, follow', false);
        // Dokumen lain tetap memakai bawaan.
        $this->get('/privasi')->assertOk()->assertSee('Data yang kami kumpulkan');
    }

    public function test_pengaturan_rusak_jatuh_ke_bawaan(): void
    {
        Pengaturan::simpan('legal', ['syarat' => [['judul' => '', 'isi' => 'x'], 'bukan-array', ['judul' => 5]], 'diperbarui' => '   ']);

        $this->get('/syarat')->assertOk()->assertSee('Penerimaan syarat')->assertSee('Terakhir diperbarui 1 Oktober 2026');
    }

    public function test_judul_dari_pengaturan_di_escape(): void
    {
        Pengaturan::simpan('legal', ['syarat' => [['judul' => '<script>x</script>', 'isi' => '<img src=y>']]]);

        $this->get('/syarat')->assertOk()->assertDontSee('<script>x</script>', false)->assertDontSee('<img src=y>', false);
    }

    public function test_dokumen_tak_dikenal_404(): void
    {
        $this->get('/kebijakan')->assertNotFound();
    }
}
