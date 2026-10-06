<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotifikasiBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_lonceng_menampilkan_badge_jumlah_belum_dibaca(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->count(3)->for($user)->create();
        Notifikasi::factory()->for($user)->dibaca()->create();
        Notifikasi::factory()->count(4)->for(User::factory())->create();

        $this->actingAs($user)->get('/beranda')
            ->assertOk()
            ->assertSee('aria-label="Notifikasi, 3 belum dibaca"', false)
            ->assertSee('data-badge-notifikasi', false);
    }

    public function test_tanpa_notifikasi_badge_tersembunyi_dan_label_nol(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/beranda')->assertOk()
            ->assertSee('aria-label="Notifikasi, 0 belum dibaca"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/<span x-show="n > 0"\s+x-cloak[^>]*data-badge-notifikasi/', $html);
    }

    public function test_badge_lebih_dari_99_ditulis_99_plus(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->count(120)->for($user)->create();

        $this->actingAs($user)->get('/beranda')->assertSee('99+', false);
    }

    public function test_notifikasi_disembunyikan_tidak_dihitung_di_badge(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->count(2)->for($user)->disembunyikan()->create();

        $this->actingAs($user)->get('/beranda')->assertSee('aria-label="Notifikasi, 0 belum dibaca"', false);
    }

    public function test_badge_hanya_memakai_satu_kueri_hitung_notifikasi(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->count(5)->for($user)->create();

        $this->actingAs($user);
        DB::enableQueryLog();
        $this->get('/beranda')->assertOk();
        $kueri = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "notifikasi"'));

        $this->assertCount(1, $kueri);
    }
}
