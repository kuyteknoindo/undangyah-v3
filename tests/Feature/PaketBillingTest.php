<?php

namespace Tests\Feature;

use App\Livewire\PaketBilling;
use App\Models\AddonPengguna;
use App\Models\ItemPesanan;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\DataTagihan;
use App\Services\Keranjang;
use App\Services\RingkasanAkun;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaketBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
    }

    private function halaman(User $user)
    {
        return Livewire::actingAs($user)->test(PaketBilling::class);
    }

    private function pesanan(User $user, array $atribut = []): Pesanan
    {
        $p = Pesanan::factory()->create(['user_id' => $user->id, 'metode_label' => 'Transfer manual'] + $atribut);
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => 'premium', 'nama' => 'Paket Premium', 'harga' => 249000]);

        return $p;
    }

    public function test_tamu_diarahkan_ke_masuk_dan_halaman_tampil_untuk_pengguna(): void
    {
        $this->get('/billing')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/billing')->assertOk()->assertSeeLivewire(PaketBilling::class);
    }

    public function test_pengguna_gratis_melihat_paket_kuota_dan_daftar_paket_dari_database(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->create(['user_id' => $user->id]);

        $this->halaman($user)
            ->assertSee('Paket Gratis')->assertSee('1 / 1')->assertSee('Kuota penuh')
            ->assertSee('Bandingkan paket')->assertSee('Rp 249.000')->assertSee('POPULER')->assertSee('Upgrade ke Premium')
            ->assertSee('Segera hadir')->assertSee('hingga 3 foto')->assertSee('Belum ada add-on yang dibeli.')
            ->assertSee('Tambah kuota undangan')->assertSee('Perlu paket berbayar')->assertSee('Belum ada pesanan');
    }

    public function test_pengguna_premium_melihat_masa_aktif_dan_kuota_lebih_besar(): void
    {
        $user = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->addDays(100)]);
        AddonPengguna::create(['user_id' => $user->id, 'addon_kode' => 'kuota-undangan', 'jumlah' => 2]);

        $this->halaman($user)
            ->assertSee('Paket Premium')->assertSee('Aktif sampai')->assertSee('Sisa masa aktif')->assertSee('100 hari')
            ->assertSee('0 / 5')->assertSee('+2 dari add-on')->assertSee('hingga 20 foto')->assertSee('Perpanjang 12 bulan')
            ->assertSee('Tambah kuota undangan ×2')->assertSee('Termasuk di paket');
    }

    public function test_premium_yang_berakhir_ditampilkan_berakhir_dan_boleh_diperpanjang(): void
    {
        $user = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->subDays(5)]);

        $this->halaman($user)->assertSee('Berakhir')->assertSee('berakhir pada')->assertSee('Perpanjang ke Premium')->assertSee('0 / 1');
    }

    public function test_ringkasan_akun_paket_nyata(): void
    {
        $r = app(RingkasanAkun::class);
        $gratis = User::factory()->create();
        $aktif = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->addDays(10)]);
        $habis = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->subDay()]);

        $this->assertSame(['kode' => 'gratis', 'nama' => 'Gratis', 'aktif' => false, 'sampai' => null, 'berakhir' => false, 'sisaHari' => null], $r->paket($gratis));
        $p = $r->paket($aktif);
        $this->assertSame(['premium', 'Premium', true, false], [$p['kode'], $p['nama'], $p['aktif'], $p['berakhir']]);
        $this->assertSame(10, $p['sisaHari']);
        $p = $r->paket($habis);
        $this->assertSame([false, true, null], [$p['aktif'], $p['berakhir'], $p['sisaHari']]);

        $this->actingAs($aktif)->get('/akun')->assertOk()->assertSee('Paket Premium')->assertSee('Kelola Paket');
    }

    public function test_tambah_dan_batalkan_add_on_ke_keranjang_sesi(): void
    {
        $user = User::factory()->create();
        $k = $this->halaman($user)->call('ubahAddon', 'live-streaming')->assertSee('Dipilih')->assertSee('Lanjut ke Checkout')->assertSee('Rp 99.000');
        $this->assertSame(['live-streaming'], app(Keranjang::class)->ambil()['addons']);

        $k->call('ubahAddon', 'live-streaming');
        $this->assertSame([], app(Keranjang::class)->ambil()['addons']);

        $k->call('ubahAddon', 'tidak-ada')->call('ubahAddon', '../../x');
        $this->assertSame([], app(Keranjang::class)->ambil()['addons']);
    }

    public function test_kosongkan_keranjang(): void
    {
        $user = User::factory()->create();
        $this->halaman($user)->call('ubahAddon', 'live-streaming')->call('kosongkanKeranjang')->assertDontSee('Lanjut ke Checkout');

        $this->assertTrue(app(Keranjang::class)->kosong());
    }

    public function test_pilih_paket_mengisi_keranjang_dan_membuka_checkout(): void
    {
        $user = User::factory()->create();

        $this->halaman($user)->call('pilihPaket', 'premium')->assertRedirect(route('checkout'));
        $this->assertSame('premium', app(Keranjang::class)->ambil()['paket']);
    }

    public function test_paket_yang_belum_bisa_dibeli_ditolak(): void
    {
        $user = User::factory()->create();

        $this->halaman($user)->call('pilihPaket', 'deluxe')->assertNoRedirect();
        $this->halaman($user)->call('pilihPaket', 'gratis')->assertNoRedirect();
        $this->halaman($user)->call('pilihPaket', 'ngawur')->assertNoRedirect();
        $this->assertNull(app(Keranjang::class)->ambil()['paket']);
    }

    public function test_riwayat_hanya_memuat_pesanan_milik_sendiri_dan_bisa_disaring(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();
        $menunggu = $this->pesanan($user);
        $lunas = $this->pesanan($user, ['status' => 'lunas', 'dibayar_at' => now()]);
        $kedaluwarsa = $this->pesanan($user, ['kedaluwarsa_at' => now()->subHour()]);
        $punyaLain = $this->pesanan($lain);

        $k = $this->halaman($user)->assertSee($menunggu->nomor)->assertSee($lunas->nomor)->assertSee($kedaluwarsa->nomor)->assertDontSee($punyaLain->nomor)
            ->assertSee('Ada pesanan yang menunggu pembayaran')->assertSee('Selesaikan pembayaran');
        $this->assertEqualsCanonicalizing([$menunggu->id, $lunas->id, $kedaluwarsa->id], $k->viewData('pesanan')->pluck('id')->all());
        $this->assertSame([$lunas->id], $k->call('setFilter', 'lunas')->viewData('pesanan')->pluck('id')->all());
        $this->assertSame([$kedaluwarsa->id], $k->call('setFilter', 'lain')->viewData('pesanan')->pluck('id')->all());
        $this->assertSame([$menunggu->id], $k->call('setFilter', 'menunggu')->viewData('pesanan')->pluck('id')->all());
        $k->call('setFilter', 'bebas')->assertSet('filter', 'semua');
    }

    public function test_kedaluwarsa_disapu_saat_halaman_dibuka_dan_banner_hilang(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, ['kedaluwarsa_at' => now()->subMinute()]);

        $this->halaman($user)->assertDontSee('Ada pesanan yang menunggu pembayaran');
        $this->assertSame('kedaluwarsa', $p->fresh()->status);
    }

    public function test_riwayat_diringkas_lalu_bisa_ditampilkan_semua(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 10) as $i) {
            $this->pesanan($user, ['status' => 'lunas']);
        }

        $k = $this->halaman($user)->assertSee('Tampilkan semua (10)');
        $this->assertCount(8, $k->viewData('pesanan'));
        $this->assertCount(10, $k->call('ubahSemua')->viewData('pesanan'));
    }

    public function test_tautan_invoice_mengarah_ke_halaman_milik_pemilik(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);

        $this->halaman($user)->assertSee(route('invoice', $p), false);
    }

    public function test_simpan_data_tagihan_valid_dan_tidak_valid(): void
    {
        $user = User::factory()->create(['name' => 'Raka']);

        $k = $this->halaman($user)->assertSet('nama', 'Raka')->assertSet('email', $user->email);
        $k->set('nama', '')->set('email', 'salah')->set('hp', '12')->call('simpanTagihan')->assertHasErrors(['nama', 'email', 'hp'])
            ->assertSee('Nama wajib diisi.')->assertSee('Masukkan email yang valid');
        $this->assertSame('Raka', app(DataTagihan::class)->ambil($user)['nama']);

        $k->set('nama', 'PT Contoh')->set('email', 'tagihan@example.test')->set('hp', '+62 812-3456-7890')->set('alamat', 'Jl. Mawar 1')->call('simpanTagihan')->assertHasNoErrors();
        $this->assertSame(['nama' => 'PT Contoh', 'email' => 'tagihan@example.test', 'hp' => '+62 812-3456-7890', 'alamat' => 'Jl. Mawar 1'], app(DataTagihan::class)->ambil($user));
        $this->assertSame(['nama', 'email', 'hp', 'alamat'], array_keys((array) Pengaturan::ambil('tagihan_u'.$user->id)));
    }

    public function test_data_tagihan_satu_pengguna_tidak_terbaca_pengguna_lain(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->halaman($a)->set('nama', 'Rahasia A')->set('email', 'a@example.test')->call('simpanTagihan');

        $this->halaman($b)->assertSet('nama', $b->name)->assertDontSee('Rahasia A');
    }
}
