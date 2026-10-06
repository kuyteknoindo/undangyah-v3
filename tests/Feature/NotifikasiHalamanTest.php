<?php

namespace Tests\Feature;

use App\Livewire\Notifikasi\Daftar;
use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class NotifikasiHalamanTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/notifikasi')->assertRedirect(route('masuk'));
    }

    public function test_akun_belum_terverifikasi_ditolak(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/notifikasi')->assertRedirect();
    }

    public function test_rute_bernama_notifikasi_menampilkan_halaman_asli_bukan_placeholder(): void
    {
        $this->assertSame('/notifikasi', route('notifikasi', absolute: false));

        $this->actingAs(User::factory()->create())->get('/notifikasi')
            ->assertOk()
            ->assertSeeLivewire(Daftar::class)
            ->assertSee('Belum ada notifikasi')
            ->assertSee('Sumber notifikasi');
    }

    public function test_daftar_hanya_menampilkan_milik_sendiri_dan_tanpa_yang_disembunyikan(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Notifikasi::factory()->for($a)->create(['judul' => 'Milik A satu']);
        Notifikasi::factory()->for($a)->disembunyikan()->create(['judul' => 'Milik A tersembunyi']);
        Notifikasi::factory()->for($b)->create(['judul' => 'Milik B rahasia']);

        Livewire::actingAs($a)->test(Daftar::class)
            ->assertSee('Milik A satu')
            ->assertDontSee('Milik A tersembunyi')
            ->assertDontSee('Milik B rahasia');
    }

    public function test_filter_belum_dibaca_dan_tipe_serta_hitungannya(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->for($user)->tipe('rsvp')->create(['judul' => 'Rsvp belum']);
        Notifikasi::factory()->for($user)->tipe('rsvp')->dibaca()->create(['judul' => 'Rsvp sudah']);
        Notifikasi::factory()->for($user)->tipe('ucapan')->create(['judul' => 'Ucapan belum']);
        Notifikasi::factory()->for($user)->tipe('pembayaran')->dibaca()->create(['judul' => 'Bayar sudah']);

        Livewire::actingAs($user)->test(Daftar::class)
            ->assertSee('Rsvp sudah')->assertSee('Bayar sudah')
            ->call('pilihFilter', 'unread')
            ->assertSee('Rsvp belum')->assertSee('Ucapan belum')->assertDontSee('Rsvp sudah')->assertDontSee('Bayar sudah')
            ->call('pilihFilter', 'rsvp')
            ->assertSee('Rsvp belum')->assertSee('Rsvp sudah')->assertDontSee('Ucapan belum')
            ->call('pilihFilter', 'ngawur')
            ->assertSet('filter', 'semua');
    }

    public function test_filter_kosong_menampilkan_pesan_dan_bisa_direset(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->for($user)->tipe('rsvp')->create();

        Livewire::actingAs($user)->test(Daftar::class)
            ->call('pilihFilter', 'kirim')
            ->assertSee('Tidak ada notifikasi untuk filter ini')
            ->call('pilihFilter', 'semua')
            ->assertDontSee('Tidak ada notifikasi untuk filter ini');
    }

    public function test_tipe_tak_dikenal_masuk_filter_sistem(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->for($user)->create(['tipe' => 'warisan', 'judul' => 'Tipe lama']);

        Livewire::actingAs($user)->test(Daftar::class)
            ->call('pilihFilter', 'sistem')
            ->assertSee('Tipe lama');
    }

    public function test_tandai_dibaca_dan_belum_dibaca(): void
    {
        $user = User::factory()->create();
        $n = Notifikasi::factory()->for($user)->create();

        $t = Livewire::actingAs($user)->test(Daftar::class)
            ->call('tandaiDibaca', $n->id)
            ->assertDispatched('notifikasi-dihitung', jumlah: 0);
        $this->assertNotNull($n->fresh()->dibaca_at);

        $t->call('tandaiBelumDibaca', $n->id)->assertDispatched('notifikasi-dihitung', jumlah: 1);
        $this->assertNull($n->fresh()->dibaca_at);
    }

    public function test_tandai_semua_dibaca(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->count(3)->for($user)->create();

        Livewire::actingAs($user)->test(Daftar::class)
            ->call('tandaiSemuaDibaca')
            ->assertDispatched('notifikasi-dihitung', jumlah: 0);

        $this->assertSame(0, $user->jumlahNotifikasiBelumDibaca());
    }

    public function test_hapus_menyembunyikan_dan_bisa_diurungkan(): void
    {
        $user = User::factory()->create();
        $n = Notifikasi::factory()->for($user)->create(['judul' => 'Akan dihapus']);

        $t = Livewire::actingAs($user)->test(Daftar::class)
            ->call('hapus', $n->id)
            ->assertSet('pesan', 'Notifikasi dihapus.')
            ->assertDontSee('Akan dihapus');
        $this->assertNotNull($n->fresh()->disembunyikan_at);
        $this->assertDatabaseHas('notifikasi', ['id' => $n->id]);

        $t->call('batalkanHapus')->assertSee('Akan dihapus')->assertSet('pesan', '');
        $this->assertNull($n->fresh()->disembunyikan_at);
    }

    public function test_hapus_yang_sudah_dibaca_dan_urungkan(): void
    {
        $user = User::factory()->create();
        $sudah = Notifikasi::factory()->count(2)->for($user)->dibaca()->create();
        $belum = Notifikasi::factory()->for($user)->create();

        $t = Livewire::actingAs($user)->test(Daftar::class)
            ->call('hapusYangDibaca')
            ->assertSet('pesan', '2 notifikasi yang sudah dibaca dihapus.');
        $this->assertSame(2, Notifikasi::whereNotNull('disembunyikan_at')->count());
        $this->assertNull($belum->fresh()->disembunyikan_at);

        $t->call('batalkanHapus');
        $this->assertSame(0, Notifikasi::whereNotNull('disembunyikan_at')->count());
        $this->assertCount(2, $sudah);
    }

    public function test_pengguna_a_tak_bisa_menandai_atau_menghapus_milik_b(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $nb = Notifikasi::factory()->for($b)->create();

        Livewire::actingAs($a)->test(Daftar::class)
            ->call('tandaiDibaca', $nb->id)
            ->call('hapus', $nb->id)
            ->assertSet('pesan', '');

        $nb->refresh();
        $this->assertNull($nb->dibaca_at);
        $this->assertNull($nb->disembunyikan_at);

        // Mengurungkan lewat daftar id yang tak diisi sendiri: layanan tetap membatasi pemilik.
        Notifikasi::whereKey($nb->id)->update(['disembunyikan_at' => now()]);
        Livewire::actingAs($a)->test(Daftar::class)->call('batalkanHapus');
        $this->assertNotNull($nb->fresh()->disembunyikan_at);
    }

    public function test_urungkan_tidak_bisa_diisi_dari_klien(): void
    {
        $user = User::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($user)->test(Daftar::class)->set('urungkan', [1, 2]);
    }

    public function test_buka_tautan_menandai_dibaca_dan_mengarahkan(): void
    {
        $user = User::factory()->create();
        $n = Notifikasi::factory()->for($user)->create(['tautan' => '/rsvp']);

        Livewire::actingAs($user)->test(Daftar::class)
            ->call('buka', $n->id)
            ->assertRedirect('/rsvp');

        $this->assertNotNull($n->fresh()->dibaca_at);
    }

    public function test_buka_notifikasi_pengguna_lain_ditolak(): void
    {
        $a = User::factory()->create();
        $nb = Notifikasi::factory()->for(User::factory())->create(['tautan' => '/rsvp']);

        Livewire::actingAs($a)->test(Daftar::class)
            ->call('buka', $nb->id)
            ->assertNotFound();

        $this->assertNull($nb->fresh()->dibaca_at);
    }

    public function test_muat_lebih_banyak(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->count(35)->for($user)->create();

        Livewire::actingAs($user)->test(Daftar::class)
            ->assertSee('Muat lebih banyak (5)')
            ->call('muatLagi')
            ->assertDontSee('Muat lebih banyak');
    }

    public function test_pengelompokan_hari_dan_tautan_tidak_aman_tidak_dirender(): void
    {
        $user = User::factory()->create();
        Notifikasi::factory()->for($user)->create(['judul' => 'Baru']);
        Notifikasi::factory()->for($user)->create(['judul' => 'Kemarin', 'created_at' => now()->subDay()->startOfDay()->addHour()]);
        Notifikasi::factory()->for($user)->create(['judul' => 'Lama', 'created_at' => now()->subDays(10)]);
        Notifikasi::factory()->for($user)->create(['judul' => 'Bahaya', 'tautan' => 'javascript:alert(1)']);

        Livewire::actingAs($user)->test(Daftar::class)
            ->assertSee('Hari ini')->assertSee('Kemarin')->assertSee('Lebih lama')
            ->assertDontSee('javascript:');
    }
}
