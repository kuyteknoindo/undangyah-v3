<?php

namespace Tests\Feature;

use App\Exceptions\PesananGagal;
use App\Livewire\Admin\DataPesanan;
use App\Livewire\Checkout;
use App\Livewire\PaketBilling;
use App\Models\AddOn;
use App\Models\ItemPesanan;
use App\Models\Notifikasi;
use App\Models\Paket;
use App\Models\Pesanan;
use App\Models\User;
use App\Services\AturanPaket;
use App\Services\HitungPesanan;
use App\Services\Keranjang;
use App\Services\KonfirmasiPesanan;
use App\Services\KuotaUndangan;
use App\Services\PembuatPesanan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

class AturanPaketTest extends TestCase
{
    use KonfirmasiSandiTes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        Paket::where('kode', 'deluxe')->update(['status' => 'aktif']);
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(10, 0));
    }

    private function aktif(string $paket, ?string $sampai = '+6 months'): User
    {
        return User::factory()->create(['paket' => $paket, 'paket_sampai' => $sampai ? now()->modify($sampai)->startOfDay() : null]);
    }

    private function buat(User $user, ?string $paket, array $addon = [])
    {
        return app(PembuatPesanan::class)->buat($user, $paket, $addon, null, 'transfer-bank', ['nama' => 'Raka', 'email' => 'raka@example.test']);
    }

    private function pesananManual(User $user, string $paket, array $addon = []): Pesanan
    {
        $p = Pesanan::factory()->create(['user_id' => $user->id, 'pembeli_nama' => 'Raka']);
        $m = Paket::where('kode', $paket)->first();
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => $paket, 'nama' => 'Paket '.$m->nama, 'harga' => $m->harga, 'efek' => ['paket' => $paket, 'durasi_bulan' => $m->durasi_bulan], 'urutan' => 0]);
        foreach ($addon as $i => $kode) {
            $a = AddOn::where('kode', $kode)->first();
            ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'addon', 'kode' => $kode, 'nama' => $a->nama, 'harga' => $a->harga, 'efek' => $a->efek, 'urutan' => $i + 1]);
        }

        return $p;
    }

    private function tanggal(User $u): string
    {
        return AturanPaket::keterangan($u->paket_sampai);
    }

    // ---- urutan tingkat

    public function test_peringkat_berasal_dari_katalog_dan_dipakai_bersama_kuota(): void
    {
        $a = app(AturanPaket::class);
        $this->assertSame(['gratis' => 0, 'premium' => 1, 'deluxe' => 2], $a->peringkat());
        $this->assertSame($a->peringkat(), app(KuotaUndangan::class)->peringkat());
        $this->assertTrue($a->lebihRendah('premium', 'deluxe'));
        $this->assertFalse($a->lebihRendah('deluxe', 'premium'));
        $this->assertFalse($a->lebihRendah('premium', 'premium'));
    }

    public function test_paket_buatan_admin_ikut_peringkat_menurut_harga(): void
    {
        Paket::create(['kode' => 'platinum', 'sku' => 'P-PLT', 'nama' => 'Platinum', 'harga' => 999000, 'durasi_bulan' => 12, 'status' => 'aktif', 'urutan' => 9]);
        $a = app(AturanPaket::class);
        $a->lupakan();

        $this->assertTrue($a->lebihRendah('deluxe', 'platinum'));
        $this->assertNotNull($a->bentrok($this->aktif('platinum'), 'deluxe'));
    }

    // ---- sama / lebih tinggi

    public function test_paket_sama_adalah_perpanjangan_dan_boleh(): void
    {
        $user = $this->aktif('premium');
        $this->assertNull(app(AturanPaket::class)->bentrok($user, 'premium'));

        $h = app(HitungPesanan::class)->hitung($user, 'premium', []);
        $this->assertTrue($h['perpanjang']);
        $this->assertNull($h['paket_galat']);

        $p = $this->buat($user, 'premium');
        $this->assertTrue(app(KonfirmasiPesanan::class)->lunas($p));
        $user->refresh();
        $this->assertSame('premium', $user->paket);
        $this->assertTrue($user->paket_sampai->isSameDay(now()->addMonths(6)->addMonths(12)));
        $this->assertFalse($p->fresh()->perluDitinjau());
    }

    public function test_paket_lebih_tinggi_adalah_upgrade_dan_boleh(): void
    {
        $user = $this->aktif('premium');
        $this->assertNull(app(AturanPaket::class)->bentrok($user, 'deluxe'));

        $p = $this->buat($user, 'deluxe');
        app(KonfirmasiPesanan::class)->lunas($p);

        $this->assertSame('deluxe', $user->fresh()->paketAktif());
        $this->assertFalse($p->fresh()->perluDitinjau());
    }

    public function test_pengguna_gratis_boleh_beli_paket_apa_pun(): void
    {
        $user = User::factory()->create();
        $this->assertNull(app(AturanPaket::class)->bentrok($user, 'premium'));
        $this->assertNull(app(AturanPaket::class)->bentrok($user, 'deluxe'));
    }

    // ---- lebih rendah ditolak

    public function test_bentrok_menyebut_paket_aktif_dan_tanggal_berakhir(): void
    {
        $user = $this->aktif('deluxe');
        $b = app(AturanPaket::class)->bentrok($user, 'premium');

        $this->assertSame('Paket Deluxe', $b['nama_aktif']);
        $this->assertSame('Paket Premium', $b['nama_beli']);
        $this->assertStringContainsString('Paket Deluxe', $b['pesan']);
        $this->assertStringContainsString($this->tanggal($user), $b['pesan']);
        $this->assertMatchesRegularExpression('/sampai \d{1,2} \w{3} 2027/', $b['pesan']);
    }

    public function test_checkout_menolak_tambah_paket_lebih_rendah(): void
    {
        $user = $this->aktif('deluxe');
        $this->actingAs($user);
        app(Keranjang::class)->isi(null, []);

        Livewire::actingAs($user)->test(Checkout::class)
            ->call('tambahPaket', 'premium')
            ->assertSee('Paket Deluxe kamu masih aktif')
            ->assertSee($this->tanggal($user));

        $this->assertNull(app(Keranjang::class)->ambil()['paket']);
    }

    public function test_checkout_dengan_paket_rendah_di_keranjang_diblokir_dengan_pesan_jelas(): void
    {
        $user = $this->aktif('deluxe');
        $this->actingAs($user);
        app(Keranjang::class)->isi('premium', []);

        $k = Livewire::actingAs($user)->test(Checkout::class)
            ->assertSee('Paket Premium tidak bisa dibeli sekarang')
            ->assertSee('Paket Deluxe kamu masih aktif')
            ->assertSee($this->tanggal($user));

        $k->set('nama', 'Raka')->set('email', 'raka@example.test')->set('hp', '0812 3456 7890')->set('alamat', 'Jakarta')->set('setuju', true)
            ->call('bayar')->assertNoRedirect();
        $this->assertSame(0, Pesanan::count());
    }

    public function test_checkout_paket_rendah_ditolak_namun_addon_dalam_keranjang_tetap_dihitung(): void
    {
        $user = $this->aktif('deluxe');
        $h = app(HitungPesanan::class)->hitung($user, 'premium', ['kuota-undangan']);

        $this->assertNull($h['paket']);
        $this->assertNotNull($h['paket_galat']);
        $this->assertSame(['kuota-undangan'], array_column($h['item'], 'kode'));
    }

    public function test_pembuat_pesanan_menolak_paket_rendah_meski_lewat_dari_keranjang(): void
    {
        $user = $this->aktif('deluxe');

        try {
            $this->buat($user, 'premium');
            $this->fail('Seharusnya ditolak.');
        } catch (PesananGagal $e) {
            $this->assertStringContainsString('Paket Deluxe', $e->getMessage());
            $this->assertStringContainsString($this->tanggal($user), $e->getMessage());
        }
        $this->assertSame(0, Pesanan::count());
    }

    public function test_pembuat_pesanan_menolak_paket_rendah_walau_model_di_memori_usang(): void
    {
        $user = User::factory()->create();
        $usang = User::find($user->id);
        $user->forceFill(['paket' => 'deluxe', 'paket_sampai' => now()->addMonths(3)])->save();

        $this->expectException(PesananGagal::class);
        $this->buat($usang, 'premium');
    }

    public function test_addon_tetap_boleh_saat_paket_tinggi_aktif(): void
    {
        $user = $this->aktif('deluxe');
        $p = $this->buat($user, null, ['kuota-undangan']);

        $this->assertSame(['kuota-undangan'], $p->item->pluck('kode')->all());
        $this->assertTrue(app(KonfirmasiPesanan::class)->lunas($p));
        $this->assertFalse($p->fresh()->perluDitinjau());
        $this->assertSame('deluxe', $user->fresh()->paketAktif());
    }

    // ---- Paket & Billing

    public function test_paket_billing_menonaktifkan_tombol_paket_lebih_rendah_dengan_penjelasan(): void
    {
        $user = $this->aktif('deluxe');

        Livewire::actingAs($user)->test(PaketBilling::class)
            ->assertSee('Tidak tersedia')
            ->assertSee('Paket Deluxe kamu masih aktif')
            ->assertSee($this->tanggal($user))
            ->assertSee('Paket yang lebih rendah tidak bisa dibeli')
            ->assertSee('Add-on tetap bisa dibeli');
    }

    public function test_paket_billing_pilih_paket_rendah_ditolak_di_server(): void
    {
        $user = $this->aktif('deluxe');

        Livewire::actingAs($user)->test(PaketBilling::class)
            ->call('pilihPaket', 'premium')
            ->assertNoRedirect()
            ->assertDispatched('toast');

        $this->assertNull(app(Keranjang::class)->ambil()['paket']);
    }

    public function test_paket_billing_perpanjang_dan_upgrade_tetap_tersedia(): void
    {
        $user = $this->aktif('premium');

        Livewire::actingAs($user)->test(PaketBilling::class)
            ->assertDontSee('Tidak tersedia')
            ->call('pilihPaket', 'premium')->assertRedirect(route('checkout'));
        Livewire::actingAs($user)->test(PaketBilling::class)
            ->call('pilihPaket', 'deluxe')->assertRedirect(route('checkout'));
    }

    // ---- kedaluwarsa

    public function test_paket_tinggi_kedaluwarsa_memperbolehkan_paket_rendah(): void
    {
        $user = User::factory()->create(['paket' => 'deluxe', 'paket_sampai' => now()->subDay()]);

        $this->assertSame('gratis', $user->paketAktif());
        $this->assertNull(app(AturanPaket::class)->bentrok($user, 'premium'));

        $p = $this->buat($user, 'premium');
        app(KonfirmasiPesanan::class)->lunas($p);

        $user->refresh();
        $this->assertSame('premium', $user->paketAktif());
        $this->assertFalse($p->fresh()->perluDitinjau());
    }

    // ---- balapan saat konfirmasi

    private function balapan(): array
    {
        $user = User::factory()->create();
        $rendah = $this->buat($user, 'premium');
        $user->forceFill(['paket' => 'deluxe', 'paket_sampai' => now()->addMonths(10)->startOfDay()])->save();

        return [$user, $rendah];
    }

    public function test_lunas_tidak_menurunkan_paket_dan_menandai_perlu_ditinjau(): void
    {
        [$user, $p] = $this->balapan();

        $this->assertTrue(app(KonfirmasiPesanan::class)->lunas($p));

        $user->refresh();
        $p->refresh();
        $this->assertSame('deluxe', $user->paket);
        $this->assertTrue($user->paket_sampai->isSameDay(now()->addMonths(10)));
        $this->assertSame(Pesanan::LUNAS, $p->status);
        $this->assertNotNull($p->dibayar_at);
        $this->assertTrue($p->perluDitinjau());
        $this->assertStringContainsString('Paket Deluxe sudah aktif sampai', $p->catatan_tinjau);
        $this->assertStringContainsString('tidak diterapkan', $p->catatan_tinjau);
        $this->assertStringContainsString('kembalikan dana', $p->catatan_tinjau);
    }

    public function test_lunas_mengirim_notifikasi_jelas_ke_pembeli_dan_admin(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $p] = $this->balapan();

        app(KonfirmasiPesanan::class)->lunas($p);

        $n = Notifikasi::where('user_id', $user->id)->first();
        $this->assertStringContainsString('paket tidak diterapkan', $n->judul);
        $this->assertStringContainsString('tidak diterapkan', $n->isi);
        $this->assertStringContainsString('mengembalikan dana', $n->isi);
        $a = Notifikasi::where('user_id', $admin->id)->first();
        $this->assertSame('Pesanan perlu ditinjau', $a->judul);
        $this->assertStringContainsString($p->nomor, $a->isi);
    }

    public function test_lunas_idempoten_pada_pesanan_perlu_ditinjau(): void
    {
        [$user, $p] = $this->balapan();
        $layanan = app(KonfirmasiPesanan::class);

        $this->assertTrue($layanan->lunas($p));
        $catatan = $p->fresh()->catatan_tinjau;
        $jumlah = Notifikasi::count();

        $this->assertFalse($layanan->lunas($p));
        $this->assertSame($catatan, $p->fresh()->catatan_tinjau);
        $this->assertSame($jumlah, Notifikasi::count());
        $this->assertSame('deluxe', $user->fresh()->paket);
    }

    public function test_lunas_paket_normal_idempoten(): void
    {
        $user = User::factory()->create();
        $p = $this->buat($user, 'premium');
        $layanan = app(KonfirmasiPesanan::class);

        $this->assertTrue($layanan->lunas($p));
        $sampai = $user->fresh()->paket_sampai;
        $this->assertFalse($layanan->lunas($p));
        $this->assertTrue($user->fresh()->paket_sampai->equalTo($sampai));
    }

    public function test_addon_dalam_pesanan_yang_sama_tetap_diterapkan_saat_paket_ditolak(): void
    {
        $user = User::factory()->create();
        $p = $this->pesananManual($user, 'premium', ['kuota-undangan']);
        $user->forceFill(['paket' => 'deluxe', 'paket_sampai' => now()->addMonths(5)])->save();

        app(KonfirmasiPesanan::class)->lunas($p);

        $this->assertSame('deluxe', $user->fresh()->paket);
        $this->assertTrue($p->fresh()->perluDitinjau());
        $this->assertSame(1, app(KuotaUndangan::class)->tambahan($user->fresh()));
    }

    public function test_pesanan_paket_rendah_saat_paket_tinggi_kedaluwarsa_diterapkan_normal(): void
    {
        $user = User::factory()->create();
        $p = $this->pesananManual($user, 'premium');
        $user->forceFill(['paket' => 'deluxe', 'paket_sampai' => now()->subDays(2)])->save();

        app(KonfirmasiPesanan::class)->lunas($p);

        $this->assertSame('premium', $user->fresh()->paketAktif());
        $this->assertFalse($p->fresh()->perluDitinjau());
    }

    // ---- penyelesaian oleh admin

    public function test_refund_pesanan_perlu_ditinjau_tidak_menurunkan_paket_aktif(): void
    {
        [$user, $p] = $this->balapan();
        $layanan = app(KonfirmasiPesanan::class);
        $layanan->lunas($p);

        $this->assertTrue($layanan->refund($p, 'Dana dikembalikan', true));

        $p->refresh();
        $this->assertSame(Pesanan::REFUND, $p->status);
        $this->assertFalse($p->perluDitinjau());
        $this->assertNotNull($p->selesai_ditinjau_at);
        $this->assertSame('deluxe', $user->fresh()->paketAktif());
    }

    public function test_selesai_tinjau_idempoten_dan_hanya_untuk_pesanan_perlu_ditinjau(): void
    {
        [$user, $p] = $this->balapan();
        $layanan = app(KonfirmasiPesanan::class);

        $this->assertFalse($layanan->selesaiTinjau($p), 'belum lunas');
        $layanan->lunas($p);
        $this->assertTrue($layanan->selesaiTinjau($p, 'Sudah transfer balik'));
        $p->refresh();
        $this->assertFalse($p->perluDitinjau());
        $this->assertNotNull($p->selesai_ditinjau_at);
        $this->assertStringContainsString('Sudah transfer balik', $p->catatan_tinjau);
        $this->assertFalse($layanan->selesaiTinjau($p));

        $normal = $this->buat(User::factory()->create(), 'premium');
        $layanan->lunas($normal);
        $this->assertFalse($layanan->selesaiTinjau($normal));
    }

    // ---- Data Pesanan admin

    public function test_data_pesanan_menampilkan_lencana_filter_dan_catatan(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $p] = $this->balapan();
        app(KonfirmasiPesanan::class)->lunas($p);
        $normal = $this->buat(User::factory()->create(), 'premium');

        $k = Livewire::actingAs($admin)->test(DataPesanan::class)
            ->assertSee('1 pesanan perlu ditinjau')
            ->assertSee('Perlu ditinjau');

        $k->call('pilihStatus', 'tinjau')
            ->assertSee($p->nomor)
            ->assertDontSee($normal->nomor);

        $k->call('buka', $p->id)
            ->assertSee('Paket Deluxe sudah aktif sampai')
            ->assertSee('kembalikan dana ke pembeli')
            ->assertSee('Tandai selesai ditinjau');
    }

    public function test_admin_menyelesaikan_tinjauan_lewat_halaman(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $p] = $this->balapan();
        app(KonfirmasiPesanan::class)->lunas($p);

        Livewire::actingAs($admin)->test(DataPesanan::class)
            ->call('tanya', $p->id, 'tinjau')
            ->set('alasan', 'Dana sudah dikembalikan')
            ->call('jalankan');

        $p->refresh();
        $this->assertFalse($p->perluDitinjau());
        $this->assertSame(Pesanan::LUNAS, $p->status);
        $this->assertStringContainsString('Dana sudah dikembalikan', $p->catatan_tinjau);
    }

    public function test_admin_refund_pesanan_perlu_ditinjau_lewat_halaman(): void
    {
        $admin = User::factory()->admin()->create();
        $this->sudahKonfirmasiSandi($admin);
        [$user, $p] = $this->balapan();
        app(KonfirmasiPesanan::class)->lunas($p);

        Livewire::actingAs($admin)->test(DataPesanan::class)
            ->call('tanya', $p->id, 'refund')
            ->set('alasan', 'Paket tinggi sudah aktif')
            ->call('jalankan');

        $this->assertSame(Pesanan::REFUND, $p->fresh()->status);
        $this->assertSame('deluxe', $user->fresh()->paketAktif());
    }

    public function test_dialog_lunas_admin_memperingatkan_paket_akan_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $p] = $this->balapan();

        Livewire::actingAs($admin)->test(DataPesanan::class)
            ->call('tanya', $p->id, 'lunas')
            ->assertSee('TIDAK diterapkan');
    }

    public function test_aksi_tinjau_ditolak_untuk_non_admin(): void
    {
        [$user, $p] = $this->balapan();
        app(KonfirmasiPesanan::class)->lunas($p);

        Livewire::actingAs(User::factory()->create())->test(DataPesanan::class)->assertForbidden();
        $this->assertTrue($p->fresh()->perluDitinjau());
    }
}
