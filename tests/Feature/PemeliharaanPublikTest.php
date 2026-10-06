<?php

namespace Tests\Feature;

use App\Models\Undangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PemeliharaanPublikTest extends TestCase
{
    use RefreshDatabase;

    public function test_undangan_terbit_tetap_terbuka_untuk_tamu_saat_pemeliharaan(): void
    {
        $pemilik = User::factory()->create();
        Undangan::factory()->terbit()->create([
            'user_id' => $pemilik->id,
            'slug' => 'raka-sinta',
            'terbitan' => ['judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'isi' => ['mempelaiPria' => 'Raka'], 'bagian' => [], 'pengaturan' => [], 'tema' => null],
        ]);
        $this->artisan('undangyah:pemeliharaan', ['status' => 'on'])->assertSuccessful();

        $this->get('/raka-sinta')->assertOk();
        $this->get('/syarat')->assertOk();
        $this->actingAs($pemilik)->get('/beranda')->assertStatus(503);
    }
}
