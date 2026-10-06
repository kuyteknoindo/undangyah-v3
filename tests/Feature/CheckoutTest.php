<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\MetodePembayaran;
use App\Models\Paket;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DataTagihan;
use App\Services\HitungPesanan;
use App\Services\Keranjang;
use App\Services\PembuatPesanan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
    }

    private function keranjang(?string $paket, array $addons = []): void
    {
        app(Keranjang::class)->isi($paket, $addons);
    }

    private function halaman(User $user, ?string $paket = 'premium', array $addons = [])
    {
        $this->actingAs($user);
        $this->keranjang($paket, $addons);

        return Livewire::actingAs($user)->test(Checkout::class);
    }

    private function isi($komponen)
    {
        return $komponen->set('nama', 'Raka Pratama')->set('email', 'raka@example.test')->set('hp', '0812 3456 7890')->set('alamat', 'Jakarta')->set('setuju', true);
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/checkout')->assertRedirect(route('masuk'));
    }

    public function test_halaman_menampilkan_ringkasan_dengan_kode_unik_dan_harga_dari_database(): void
    {
        $user = User::factory()->create();
        $k = $this->halaman($user);

        $k->assertSee('Paket Premium')->assertSee('Rp 249.000')->assertSee('Total transfer');
        $this->assertSame(249000, app(HitungPesanan::class)->hitung($user, 'premium', [])['subtotal']);
    }

    public function test_harga_mengikuti_database_bukan_input_klien(): void
    {
        $user = User::factory()->create();
        Paket::where('kode', 'premium')->update(['harga' => 100000]);

        $this->isi($this->halaman($user))->call('bayar')->assertRedirect();

        $p = Pesanan::first();
        $this->assertSame(100000, $p->subtotal);
        $this->assertSame(100000, $p->item->first()->harga);
        $this->assertSame(100000 + $p->kode_unik, $p->total);
    }

    public function test_properti_sensitif_tidak_bisa_diubah_klien(): void
    {
        $k = $this->halaman(User::factory()->create());

        foreach (['voucher' => 'HEMAT50K', 'unik' => 1] as $nama => $nilai) {
            try {
                $k->set($nama, $nilai);
                $this->fail("Properti $nama seharusnya terkunci");
            } catch (CannotUpdateLockedPropertyException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_voucher_persen_valid_memotong_harga(): void
    {
        $user = User::factory()->create();
        $k = $this->halaman($user)->set('vInput', 'nikah20')->call('pakaiVoucher')->assertSet('voucher', 'NIKAH20')->assertSee('hemat Rp 49.800');

        $this->isi($k)->call('bayar');
        $p = Pesanan::first();
        $this->assertSame(49800, $p->diskon);
        $this->assertSame('NIKAH20', $p->voucher_kode);
        $this->assertSame(249000 - 49800 + $p->kode_unik, $p->total);
        $this->assertSame(65, Voucher::where('kode', 'NIKAH20')->value('terpakai'), 'voucher ditahan sejak pesanan dibuat');
    }

    public function test_voucher_ditolak_untuk_semua_kasus_gagal(): void
    {
        $user = User::factory()->create();
        Voucher::create(['kode' => 'MIN500K', 'jenis' => 'persen', 'nilai' => 10, 'min_belanja' => 500000]);
        Voucher::create(['kode' => 'NONAKTIF', 'jenis' => 'persen', 'nilai' => 10, 'aktif' => false]);
        Voucher::create(['kode' => 'BESOK', 'jenis' => 'persen', 'nilai' => 10, 'berlaku_dari' => now()->addDay()]);

        $kasus = [
            'TIDAKADA' => 'Kode voucher tidak ditemukan.',
            'HEMAT50K' => 'Voucher ini sudah habis dipakai.',
            'MERDEKA17' => 'Voucher ini sudah kedaluwarsa.',
            'NONAKTIF' => 'Voucher ini sedang tidak aktif.',
            'BESOK' => 'Voucher ini belum berlaku.',
            'MIN500K' => 'Voucher ini berlaku untuk belanja minimal Rp 500.000.',
        ];
        foreach ($kasus as $kode => $pesan) {
            $this->halaman($user)->set('vInput', $kode)->call('pakaiVoucher')->assertSet('voucher', '')->assertSet('pesanVoucher', $pesan);
        }
        $this->halaman($user)->set('vInput', '')->call('pakaiVoucher')->assertSet('pesanVoucher', 'Masukkan kode voucher dulu.');
    }

    public function test_voucher_khusus_premium_ditolak_untuk_pembeli_add_on_saja(): void
    {
        $user = User::factory()->create();

        $this->halaman($user, null, ['live-streaming'])->set('vInput', 'PREMIUM10')->call('pakaiVoucher')
            ->assertSet('voucher', '')->assertSet('pesanVoucher', 'Voucher PREMIUM10 hanya berlaku untuk paket Premium.');
        $this->halaman($user, 'premium')->set('vInput', 'PREMIUM10')->call('pakaiVoucher')->assertSet('voucher', 'PREMIUM10');
    }

    public function test_voucher_nominal_dan_batas_diskon(): void
    {
        $user = User::factory()->create();
        Voucher::create(['kode' => 'POTONG', 'jenis' => 'nominal', 'nilai' => 1000000]);
        Voucher::create(['kode' => 'MAKS', 'jenis' => 'persen', 'nilai' => 50, 'maks_diskon' => 30000]);

        $a = app(HitungPesanan::class)->hitung($user, null, ['live-streaming'], 'POTONG');
        $this->assertSame(99000, $a['diskon'], 'potongan tidak boleh melebihi subtotal');
        $b = app(HitungPesanan::class)->hitung($user, 'premium', [], 'MAKS');
        $this->assertSame(30000, $b['diskon']);
    }

    public function test_pemakaian_voucher_dibatasi_laju(): void
    {
        $user = User::factory()->create();
        $k = $this->halaman($user);
        for ($i = 0; $i < 10; $i++) {
            $k->set('vInput', 'SALAH'.$i)->call('pakaiVoucher');
        }

        $k->set('vInput', 'NIKAH20')->call('pakaiVoucher')->assertSet('voucher', '')->assertSee('Terlalu banyak percobaan voucher');
    }

    public function test_voucher_yang_tidak_lagi_valid_membatalkan_pembuatan_pesanan(): void
    {
        $user = User::factory()->create();
        $k = $this->isi($this->halaman($user)->set('vInput', 'NIKAH20')->call('pakaiVoucher'));
        Voucher::where('kode', 'NIKAH20')->update(['aktif' => false]);

        $k->call('bayar')->assertSet('galat', 'Voucher ini sedang tidak aktif.')->assertNoRedirect();
        $this->assertSame(0, Pesanan::count());
    }

    public function test_bayar_membuat_pesanan_lengkap_dan_mengosongkan_keranjang(): void
    {
        $user = User::factory()->create();
        $k = $this->isi($this->halaman($user, 'premium', ['kuota-undangan']))->call('bayar');

        $p = Pesanan::with('item')->first();
        $k->assertRedirect(route('konfirmasi-pembayaran', ['pesanan' => $p->nomor]));
        $this->assertMatchesRegularExpression('/^INV-\d{4}-0001$/', $p->nomor);
        $this->assertSame($user->id, $p->user_id);
        $this->assertSame('menunggu', $p->status);
        $this->assertSame(274000, $p->subtotal);
        $this->assertGreaterThanOrEqual(101, $p->kode_unik);
        $this->assertLessThanOrEqual(999, $p->kode_unik);
        $this->assertSame(274000 + $p->kode_unik, $p->total);
        $this->assertSame(['paket:premium', 'addon:kuota-undangan'], $p->item->map(fn ($i) => $i->tipe.':'.$i->kode)->all());
        $this->assertTrue($p->kedaluwarsa_at->between(now()->addHours(23), now()->addHours(25)));
        $this->assertSame('Transfer manual', $p->metode_label);
        $this->assertSame('BANK CONTOH', $p->metode_data['bank']);
        $this->assertSame('Raka Pratama', $p->pembeli_nama);
        $this->assertTrue(app(Keranjang::class)->kosong());
        $this->assertSame('Raka Pratama', app(DataTagihan::class)->ambil($user)['nama']);
    }

    public function test_pesanan_menunggu_yang_sama_tidak_digandakan(): void
    {
        $user = User::factory()->create();
        $this->isi($this->halaman($user))->call('bayar');
        $this->isi($this->halaman($user))->call('bayar')->assertRedirect(route('konfirmasi-pembayaran', ['pesanan' => Pesanan::first()->nomor]));

        $this->assertSame(1, Pesanan::count());
    }

    public function test_batas_tiga_pesanan_menunggu(): void
    {
        $user = User::factory()->create();
        foreach (['live-streaming', 'blast-whatsapp', 'musik-premium'] as $kode) {
            $this->isi($this->halaman($user, null, [$kode]))->call('bayar');
        }
        $this->assertSame(3, Pesanan::count());

        $this->isi($this->halaman($user, null, ['tema-eksklusif']))->call('bayar')->assertNoRedirect()->assertSet('galat', 'Kamu punya 3 pesanan yang menunggu pembayaran. Selesaikan atau batalkan salah satunya dulu.');
        $this->assertSame(3, Pesanan::count());
    }

    public function test_validasi_data_penagihan_dan_persetujuan(): void
    {
        $user = User::factory()->create();

        $this->halaman($user)->set('nama', '')->set('email', 'bukan-email')->set('hp', 'abc')->set('setuju', false)->call('bayar')
            ->assertHasErrors(['nama' => 'required', 'email' => 'email', 'hp' => 'regex', 'setuju' => 'accepted'])
            ->assertSee('Nama wajib diisi.')->assertSee('Masukkan email yang valid')->assertSee('Centang persetujuan untuk melanjutkan.');
        $this->halaman($user)->set('nama', 'A')->set('email', 'a@example.test')->set('hp', '123')->set('setuju', true)->call('bayar')
            ->assertHasErrors(['hp'])->assertSee('Nomor HP tidak valid. Gunakan 9 sampai 15 digit.');
        $this->assertSame(0, Pesanan::count());
    }

    public function test_keranjang_kosong_atau_item_tak_valid_tidak_bisa_dibayar(): void
    {
        $user = User::factory()->create();

        $this->isi($this->halaman($user, null))->call('bayar')->assertSet('galat', 'Keranjang kosong atau item tidak bisa dibeli.');
        $this->isi($this->halaman($user, 'deluxe'))->call('bayar')->assertSet('galat', 'Keranjang kosong atau item tidak bisa dibeli.');
        $this->isi($this->halaman($user, 'gratis'))->call('bayar')->assertSet('galat', 'Keranjang kosong atau item tidak bisa dibeli.');
        $this->isi($this->halaman($user, null, ['tidak-ada']))->call('bayar')->assertSet('galat', 'Keranjang kosong atau item tidak bisa dibeli.');
        $this->assertSame(0, Pesanan::count());
    }

    public function test_metode_tidak_valid_atau_nonaktif_ditolak(): void
    {
        $user = User::factory()->create();

        $k = $this->isi($this->halaman($user))->set('metode', 'bitcoin')->call('bayar')->assertSet('galat', 'Pilih metode pembayaran yang tersedia.');
        $k->call('pilihMetode', 'dompet-digital')->assertSet('metode', 'bitcoin'); // nonaktif: diabaikan
        $this->assertSame(0, Pesanan::count());
    }

    public function test_aturan_add_on_diterapkan_di_server(): void
    {
        $user = User::factory()->create();
        $h = app(HitungPesanan::class);

        // Butuh premium: dibuang untuk pengguna gratis, dibolehkan bila ikut membeli premium.
        $this->assertSame([], $h->hitung($user, null, ['perpanjang-masa-aktif'])['item']);
        $this->assertCount(2, $h->hitung($user, 'premium', ['perpanjang-masa-aktif'])['item']);
        // Termasuk di premium: tidak ditagih bila paket premium ikut dibeli.
        $this->assertCount(1, $h->hitung($user, 'premium', ['hapus-logo'])['item']);
        $this->assertCount(1, $h->hitung($user, null, ['hapus-logo'])['item']);
        // Sekali beli yang sudah dimiliki dibuang; multi boleh lagi.
        AddonPengguna::create(['user_id' => $user->id, 'addon_kode' => 'domain-kustom', 'jumlah' => 1]);
        AddonPengguna::create(['user_id' => $user->id, 'addon_kode' => 'kuota-undangan', 'jumlah' => 2]);
        $this->assertSame([], $h->hitung($user, null, ['domain-kustom'])['item']);
        $this->assertCount(1, $h->hitung($user, null, ['kuota-undangan'])['item']);
        // Add-on nonaktif tidak bisa dibeli.
        AddOn::where('kode', 'live-streaming')->update(['aktif' => false]);
        $this->assertSame([], $h->hitung($user, null, ['live-streaming'])['item']);
    }

    public function test_paket_nonaktif_atau_draf_tidak_bisa_dibeli(): void
    {
        $user = User::factory()->create();
        Paket::where('kode', 'premium')->update(['status' => 'arsip']);

        $this->assertSame([], app(HitungPesanan::class)->hitung($user, 'premium', [])['item']);
    }

    public function test_total_nol_karena_voucher_langsung_lunas_otomatis(): void
    {
        $user = User::factory()->create();
        Voucher::create(['kode' => 'GRATIS100', 'jenis' => 'persen', 'nilai' => 100]);

        $this->isi($this->halaman($user)->set('vInput', 'GRATIS100')->call('pakaiVoucher'))->call('bayar')->assertRedirect();

        $p = Pesanan::first();
        $this->assertSame(0, $p->total);
        $this->assertSame(0, $p->kode_unik);
        $this->assertSame('lunas', $p->status);
        $this->assertSame('premium', $user->fresh()->paket);
    }

    public function test_pembuatan_pesanan_dibatasi_laju(): void
    {
        $user = User::factory()->create();
        $k = $this->halaman($user)->set('nama', '');
        for ($i = 0; $i < 5; $i++) {
            $k->call('bayar');
        }

        $k->call('bayar')->assertSet('galat', 'Terlalu banyak percobaan membuat pesanan. Tunggu sebentar lalu coba lagi.');
    }

    public function test_kode_unik_tidak_bentrok_dengan_pesanan_menunggu_lain(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->isi($this->halaman($a))->call('bayar');
        $pertama = Pesanan::first();

        // Paksa pratinjau pembeli kedua memakai kode unik yang sama.
        $pembuat = app(PembuatPesanan::class);
        $kedua = $pembuat->buat($b, 'premium', [], null, 'transfer-bank', ['nama' => 'B', 'email' => 'b@example.test'], $pertama->kode_unik);

        $this->assertNotSame($pertama->total, $kedua->total);
        $this->assertSame(249000 + $kedua->kode_unik, $kedua->total);
    }

    public function test_metode_nonaktif_dari_katalog_tidak_dipakai_di_tampilan(): void
    {
        MetodePembayaran::where('kode', 'transfer-bank')->update(['aktif' => false]);
        $user = User::factory()->create();

        $this->halaman($user)->assertSee('Belum ada metode pembayaran aktif');
    }
}
