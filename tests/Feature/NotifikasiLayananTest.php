<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use App\Services\Notifikasi as Layanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifikasiLayananTest extends TestCase
{
    use RefreshDatabase;

    private function layanan(): Layanan
    {
        return app(Layanan::class);
    }

    public function test_kirim_menyimpan_notifikasi_untuk_pengguna_dan_menerima_id(): void
    {
        $user = User::factory()->create();

        $n = $this->layanan()->kirim($user, 'rsvp', 'RSVP baru', 'Budi hadir', '/rsvp', ['undangan_id' => 7]);
        $lain = $this->layanan()->kirim($user->id, 'pembayaran', 'Lunas', 'Terima kasih');

        $this->assertSame($user->id, $n->user_id);
        $this->assertSame('rsvp', $n->tipe);
        $this->assertSame('/rsvp', $n->tautan);
        $this->assertSame(['undangan_id' => 7], $n->fresh()->data);
        $this->assertNull($n->dibaca_at);
        $this->assertNull($lain->tautan);
        $this->assertNull($lain->data);
        $this->assertSame(2, $this->layanan()->jumlahBelumDibaca($user));
    }

    public function test_tipe_tak_dikenal_menjadi_sistem_dan_tautan_berbahaya_dibuang(): void
    {
        $user = User::factory()->create();

        $a = $this->layanan()->kirim($user, 'ngawur', 'J', 'I', 'javascript:alert(1)');
        $b = $this->layanan()->kirim($user, 'sistem', 'J', 'I', '//jahat.example/x');
        $c = $this->layanan()->kirim($user, 'sistem', 'J', 'I', 'https://contoh.test/a');

        $this->assertSame('sistem', $a->tipe);
        $this->assertNull($a->tautan);
        $this->assertNull($b->tautan);
        $this->assertSame('https://contoh.test/a', $c->tautan);
    }

    public function test_jumlah_belum_dibaca_hanya_milik_pengguna_dan_tanpa_yang_disembunyikan(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();
        Notifikasi::factory()->count(2)->for($user)->create();
        Notifikasi::factory()->for($user)->dibaca()->create();
        Notifikasi::factory()->for($user)->disembunyikan()->create();
        Notifikasi::factory()->count(5)->for($lain)->create();

        $this->assertSame(2, $this->layanan()->jumlahBelumDibaca($user));
        $this->assertSame(2, $user->jumlahNotifikasiBelumDibaca());
        $this->assertSame(5, $this->layanan()->jumlahBelumDibaca($lain->id));
    }

    public function test_tandai_dibaca_dan_belum_dibaca(): void
    {
        $user = User::factory()->create();
        $n = Notifikasi::factory()->for($user)->create();

        $this->assertSame(1, $this->layanan()->tandaiDibaca($user, $n->id));
        $this->assertNotNull($n->fresh()->dibaca_at);
        $this->assertSame(0, $this->layanan()->tandaiDibaca($user, $n->id));

        $this->assertSame(1, $this->layanan()->tandaiBelumDibaca($user, [$n->id]));
        $this->assertNull($n->fresh()->dibaca_at);
    }

    public function test_tandai_semua_dibaca_tidak_menyentuh_pengguna_lain(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();
        Notifikasi::factory()->count(3)->for($user)->create();
        $punyaLain = Notifikasi::factory()->for($lain)->create();

        $this->assertSame(3, $this->layanan()->tandaiSemuaDibaca($user));

        $this->assertSame(0, $this->layanan()->jumlahBelumDibaca($user));
        $this->assertNull($punyaLain->fresh()->dibaca_at);
    }

    public function test_sembunyikan_dan_pulihkan(): void
    {
        $user = User::factory()->create();
        $n = Notifikasi::factory()->for($user)->create();

        $this->assertSame(1, $this->layanan()->sembunyikan($user, $n->id));
        $this->assertNotNull($n->fresh()->disembunyikan_at);
        $this->assertSame(0, $this->layanan()->jumlahBelumDibaca($user));

        $this->assertSame(1, $this->layanan()->pulihkan($user, $n->id));
        $this->assertNull($n->fresh()->disembunyikan_at);
        $this->assertSame(1, $this->layanan()->jumlahBelumDibaca($user));
    }

    public function test_sembunyikan_yang_dibaca_mengembalikan_id(): void
    {
        $user = User::factory()->create();
        $dibaca = Notifikasi::factory()->count(2)->for($user)->dibaca()->create();
        $belum = Notifikasi::factory()->for($user)->create();

        $ids = $this->layanan()->sembunyikanYangDibaca($user);

        $this->assertEqualsCanonicalizing($dibaca->pluck('id')->all(), $ids->all());
        $this->assertNull($belum->fresh()->disembunyikan_at);
    }

    public function test_pengguna_tak_bisa_mengubah_notifikasi_pengguna_lain_lewat_layanan(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $nb = Notifikasi::factory()->for($b)->create();

        $this->assertSame(0, $this->layanan()->tandaiDibaca($a, $nb->id));
        $this->assertSame(0, $this->layanan()->sembunyikan($a, $nb->id));
        $this->assertSame(0, $this->layanan()->pulihkan($a, $nb->id));

        $nb->refresh();
        $this->assertNull($nb->dibaca_at);
        $this->assertNull($nb->disembunyikan_at);
    }

    public function test_notifikasi_ikut_terhapus_saat_pengguna_dihapus(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->count(2)->for($user)->create();

        $user->delete();

        $this->assertSame(0, Notifikasi::count());
    }
}
