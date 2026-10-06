<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HalamanGalatTest extends TestCase
{
    use RefreshDatabase;

    public function test_kode_galat_umum_tampil_ramah_berbahasa_indonesia(): void
    {
        Route::get('/_galat/{kode}', fn (int $kode) => abort($kode))->middleware('web');
        Route::get('/_galat-pesan', fn () => abort(429, 'Terlalu banyak unduhan. Coba lagi nanti.'))->middleware('web');

        $this->get('/_galat/403')->assertForbidden()->assertSee('Akses ditolak')->assertDontSee('Forbidden');
        $this->get('/_galat/419')->assertStatus(419)->assertSee('Halaman kedaluwarsa')->assertDontSee('Page Expired');
        $this->get('/_galat/429')->assertStatus(429)->assertSee('Terlalu banyak permintaan');
        $this->get('/_galat-pesan')->assertStatus(429)->assertSee('Terlalu banyak unduhan. Coba lagi nanti.');
        $this->get('/_galat/500')->assertStatus(500)->assertSee('Terjadi kesalahan di server')->assertDontSee('Server Error');
        $this->get('/_galat/503')->assertStatus(503)->assertSee('Layanan sedang tidak tersedia');
    }

    public function test_non_admin_ke_panel_admin_mendapat_403_ramah(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')
            ->assertForbidden()->assertSee('Kamu tidak punya akses ke halaman ini');
    }
}
