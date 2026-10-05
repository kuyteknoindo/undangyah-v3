<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TataLetakTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/beranda')->assertRedirect(route('masuk'));
        $this->get('/')->assertRedirect('/beranda');
    }

    public function test_pengguna_melihat_sidebar_dan_header_tanpa_menu_admin(): void
    {
        $user = User::factory()->create(['name' => 'Deni Saputra']);

        $this->actingAs($user)->get('/beranda')
            ->assertOk()
            ->assertSee('Selamat', false)
            ->assertSee('Deni')
            ->assertSee('Buat Undangan')
            ->assertSee('RSVP &amp; Tamu', false)
            ->assertSee('Paket Gratis')
            ->assertSee('Keluar')
            ->assertSee('DS') // inisial di header
            ->assertDontSee('Panel Admin')
            ->assertDontSee('Theme Creator');
    }

    public function test_admin_melihat_menu_panel_admin_dengan_semua_halaman(): void
    {
        $admin = User::factory()->admin()->create();

        $respons = $this->actingAs($admin)->get('/beranda')->assertOk()->assertSee('Panel Admin');

        foreach (['Theme Creator', 'Media', 'Musik', 'Product', 'Addons Product', 'Voucher', 'Data Pesanan', 'Invoices',
            'Metode Pembayaran', 'Users', 'Notifikasi', 'OTP Setting', 'Storage', 'Backup dan Restore'] as $label) {
            $respons->assertSee($label);
        }
    }

    public function test_rute_admin_hanya_untuk_peran_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/media')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/media')
            ->assertOk()->assertSee('Panel Admin')->assertSee('Media');
    }

    public function test_halaman_menu_yang_belum_dibuat_tampil_sebagai_placeholder(): void
    {
        $this->actingAs(User::factory()->create())->get('/statistik')
            ->assertOk()->assertSee('Statistik')->assertSee('dibuat di tahap berikutnya');
    }

    public function test_keluar_mengakhiri_sesi(): void
    {
        $this->actingAs(User::factory()->create())->post('/keluar')->assertRedirect(route('masuk'));
        $this->assertGuest();
    }

    public function test_zona_waktu_dan_bahasa(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('id', app()->getLocale());
    }

    public function test_ip_klien_dibaca_dari_cf_connecting_ip_hanya_lewat_proxy_lokal(): void
    {
        $this->app['router']->get('/_ip', fn (Request $r) => $r->ip())->middleware('web');

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/_ip', ['CF-Connecting-IP' => '203.0.113.7'])->assertSee('203.0.113.7');

        // Header dari klien yang bukan proxy lokal harus diabaikan (anti-spoof).
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->get('/_ip', ['CF-Connecting-IP' => '203.0.113.7'])->assertSee('198.51.100.20');
    }
}
