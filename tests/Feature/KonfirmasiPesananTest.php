<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\ItemPesanan;
use App\Models\MetodePembayaran;
use App\Models\Notifikasi;
use App\Models\Paket;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\KonfirmasiPesanan;
use App\Services\KuotaUndangan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KonfirmasiPesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(10, 0));
    }

    private function layanan(): KonfirmasiPesanan
    {
        return app(KonfirmasiPesanan::class);
    }

    /** @param  list<array{0:string,1:string}>  $item  [tipe, kode] */
    private function pesanan(User $user, array $item, array $atribut = []): Pesanan
    {
        $p = Pesanan::factory()->create(['user_id' => $user->id] + $atribut);
        foreach ($item as $i => [$tipe, $kode]) {
            if ($tipe === 'paket') {
                $m = Paket::where('kode', $kode)->first();
                ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => $kode, 'nama' => 'Paket '.$m->nama, 'harga' => $m->harga, 'efek' => ['paket' => $kode, 'durasi_bulan' => $m->durasi_bulan], 'urutan' => $i]);
            } else {
                $m = AddOn::where('kode', $kode)->first();
                ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'addon', 'kode' => $kode, 'nama' => $m->nama, 'harga' => $m->harga, 'efek' => $m->efek, 'urutan' => $i]);
            }
        }

        return $p;
    }

    public function test_lunas_mengaktifkan_premium_12_bulan_dan_mengirim_notifikasi(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, [['paket', 'premium']]);

        $this->assertTrue($this->layanan()->lunas($p));

        $user->refresh();
        $this->assertSame('premium', $user->paket);
        $this->assertTrue($user->paket_sampai->isSameDay(now()->addMonthsNoOverflow(12)));
        $this->assertSame('premium', $user->paketAktif());
        $this->assertSame('lunas', $p->status);
        $this->assertNotNull($p->dibayar_at);
        $n = Notifikasi::where('user_id', $user->id)->first();
        $this->assertSame('pembayaran', $n->tipe);
        $this->assertStringContainsString($p->nomor, $n->isi);
        $this->assertStringContainsString('Paket Premium aktif sampai', $n->isi);
    }

    public function test_lunas_memperpanjang_dari_masa_aktif_yang_masih_berlaku(): void
    {
        $sampai = now()->addMonths(2)->startOfDay();
        $user = User::factory()->create(['paket' => 'premium', 'paket_sampai' => $sampai]);
        $p = $this->pesanan($user, [['paket', 'premium']]);

        $this->layanan()->lunas($p);

        $this->assertTrue($user->fresh()->paket_sampai->isSameDay($sampai->copy()->addMonthsNoOverflow(12)));
    }

    public function test_premium_yang_sudah_berakhir_dihitung_dari_sekarang(): void
    {
        $user = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->subMonths(3)]);
        $this->assertSame('gratis', $user->paketAktif());
        $p = $this->pesanan($user, [['paket', 'premium']]);

        $this->layanan()->lunas($p);

        $this->assertTrue($user->fresh()->paket_sampai->isSameDay(now()->addMonthsNoOverflow(12)));
    }

    public function test_aktivasi_ganda_tidak_menggandakan_apa_pun(): void
    {
        $user = User::factory()->create();
        $v = Voucher::where('kode', 'NIKAH20')->first();
        $awal = $v->terpakai;
        $p = $this->pesanan($user, [['paket', 'premium'], ['addon', 'kuota-undangan']], ['voucher_id' => $v->id, 'voucher_kode' => 'NIKAH20', 'voucher_ditahan' => false]);

        $this->assertTrue($this->layanan()->lunas($p));
        $sampai = $user->fresh()->paket_sampai;
        $this->assertFalse($this->layanan()->lunas($p));
        $this->assertFalse($this->layanan()->lunas(Pesanan::find($p->id)));

        $this->assertTrue($user->fresh()->paket_sampai->equalTo($sampai));
        $this->assertSame(1, AddonPengguna::where('user_id', $user->id)->value('jumlah'));
        $this->assertSame($awal + 1, $v->fresh()->terpakai);
        $this->assertSame(1, Notifikasi::where('user_id', $user->id)->count());
    }

    public function test_voucher_yang_ditahan_saat_dibuat_tidak_dihitung_lagi_saat_lunas(): void
    {
        $user = User::factory()->create();
        $v = Voucher::where('kode', 'NIKAH20')->first();
        $p = $this->pesanan($user, [['paket', 'premium']], ['voucher_id' => $v->id, 'voucher_kode' => 'NIKAH20', 'voucher_ditahan' => true]);

        $this->layanan()->lunas($p);

        $this->assertSame($v->terpakai, $v->fresh()->terpakai);
    }

    public function test_addon_kuota_menambah_batas_undangan(): void
    {
        $user = User::factory()->create();
        $kuota = app(KuotaUndangan::class);
        $this->assertSame(1, $kuota->batas($user));

        $this->layanan()->lunas($this->pesanan($user, [['addon', 'kuota-undangan']]));
        $this->layanan()->lunas($this->pesanan($user, [['addon', 'kuota-undangan']]));

        $this->assertSame(3, $kuota->batas($user->fresh()));
        $this->assertSame(2, AddonPengguna::jumlahMilik($user->id)['kuota-undangan']);
    }

    public function test_kuota_mengikuti_paket_premium_dan_kembali_gratis_setelah_berakhir(): void
    {
        $user = User::factory()->create();
        $kuota = app(KuotaUndangan::class);
        $this->layanan()->lunas($this->pesanan($user, [['paket', 'premium']]));
        $this->assertSame(3, $kuota->batas($user->fresh()));

        $this->travel(13)->months();
        $this->assertSame(1, $kuota->batas($user->fresh()));
    }

    public function test_addon_perpanjang_menambah_tiga_bulan_setelah_paket_diaktifkan(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, [['addon', 'perpanjang-masa-aktif'], ['paket', 'premium']]);

        $this->layanan()->lunas($p);

        $this->assertTrue($user->fresh()->paket_sampai->isSameDay(now()->addMonthsNoOverflow(15)));
    }

    public function test_addon_perpanjang_tanpa_paket_aktif_tidak_mengubah_tanggal_dan_dicatat(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, [['addon', 'perpanjang-masa-aktif']]);

        $this->layanan()->lunas($p);

        $this->assertNull($user->fresh()->paket_sampai);
        $this->assertStringContainsString('tinjau manual', $p->fresh()->catatan_admin);
    }

    public function test_addon_sekali_beli_tidak_bertambah_jumlahnya(): void
    {
        $user = User::factory()->create();
        $this->layanan()->lunas($this->pesanan($user, [['addon', 'domain-kustom']]));
        $this->layanan()->lunas($this->pesanan($user, [['addon', 'domain-kustom']]));

        $this->assertSame(1, AddonPengguna::where('user_id', $user->id)->where('addon_kode', 'domain-kustom')->value('jumlah'));
    }

    public function test_lunas_dari_kedaluwarsa_diizinkan_tetapi_dari_batal_tidak(): void
    {
        $user = User::factory()->create();
        $v = Voucher::where('kode', 'NIKAH20')->first();
        $kedaluwarsa = $this->pesanan($user, [['paket', 'premium']], ['status' => 'kedaluwarsa', 'voucher_id' => $v->id, 'voucher_kode' => 'NIKAH20', 'voucher_ditahan' => false]);
        $batal = $this->pesanan($user, [['addon', 'live-streaming']], ['status' => 'batal']);

        $this->assertTrue($this->layanan()->lunas($kedaluwarsa));
        $this->assertSame($v->terpakai + 1, $v->fresh()->terpakai);
        $this->assertFalse($this->layanan()->lunas($batal));
        $this->assertSame('batal', $batal->fresh()->status);
        $this->assertSame(0, AddonPengguna::where('addon_kode', 'live-streaming')->count());
    }

    public function test_tolak_membuang_bukti_dan_meminta_unggah_ulang_secara_idempoten(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bukti-pembayaran/a.png', 'x');
        $user = User::factory()->create();
        $p = $this->pesanan($user, [['paket', 'premium']], ['bukti_path' => 'bukti-pembayaran/a.png', 'bukti_nama' => 'a.png', 'bukti_at' => now()]);

        $this->assertTrue($this->layanan()->tolak($p, 'Nominal tidak sesuai'));
        $this->assertFalse($this->layanan()->tolak($p, 'Nominal tidak sesuai'));

        $p->refresh();
        $this->assertNull($p->bukti_path);
        $this->assertNotNull($p->bukti_ditolak_at);
        $this->assertSame('menunggu', $p->status);
        $this->assertSame('Nominal tidak sesuai', $p->catatan_admin);
        Storage::disk('local')->assertMissing('bukti-pembayaran/a.png');
        $this->assertSame(1, Notifikasi::where('user_id', $user->id)->count());
    }

    public function test_tolak_dengan_batalkan_membatalkan_dan_mengembalikan_voucher(): void
    {
        $user = User::factory()->create();
        $v = Voucher::where('kode', 'NIKAH20')->first();
        $v->update(['terpakai' => 10]);
        $p = $this->pesanan($user, [['paket', 'premium']], ['voucher_id' => $v->id, 'voucher_kode' => 'NIKAH20', 'voucher_ditahan' => true]);

        $this->assertTrue($this->layanan()->tolak($p, 'Penipuan', batalkan: true));
        $this->assertFalse($this->layanan()->tolak($p, 'Penipuan', batalkan: true));

        $this->assertSame('batal', $p->fresh()->status);
        $this->assertSame(9, $v->fresh()->terpakai);
        $this->assertFalse($p->fresh()->voucher_ditahan);
    }

    public function test_tolak_tanpa_bukti_tidak_mengubah_apa_pun(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, [['paket', 'premium']]);

        $this->assertFalse($this->layanan()->tolak($p, 'x'));
        $this->assertSame(0, Notifikasi::count());
    }

    public function test_pengguna_membatalkan_dan_voucher_dikembalikan_sekali(): void
    {
        $user = User::factory()->create();
        $v = Voucher::where('kode', 'NIKAH20')->first();
        $p = $this->pesanan($user, [['paket', 'premium']], ['voucher_id' => $v->id, 'voucher_kode' => 'NIKAH20', 'voucher_ditahan' => true]);

        $this->assertTrue($this->layanan()->batalkan($p));
        $this->assertFalse($this->layanan()->batalkan($p));

        $this->assertSame('batal', $p->fresh()->status);
        $this->assertSame($v->terpakai - 1, $v->fresh()->terpakai);
    }

    public function test_pesanan_lunas_tidak_bisa_dibatalkan_atau_dikedaluwarsakan(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, [['paket', 'premium']]);
        $this->layanan()->lunas($p);

        $this->assertFalse($this->layanan()->batalkan($p));
        $this->travel(3)->days();
        $this->assertFalse($this->layanan()->kedaluwarsakan($p));
        $this->assertSame('lunas', $p->fresh()->status);
    }

    public function test_kedaluwarsakan_hanya_setelah_batas_dan_mengembalikan_voucher(): void
    {
        $user = User::factory()->create();
        $v = Voucher::where('kode', 'NIKAH20')->first();
        $p = $this->pesanan($user, [['paket', 'premium']], ['voucher_id' => $v->id, 'voucher_kode' => 'NIKAH20', 'voucher_ditahan' => true]);

        $this->assertFalse($this->layanan()->kedaluwarsakan($p));
        $this->travel(25)->hours();
        $this->assertTrue($this->layanan()->kedaluwarsakan($p));
        $this->assertFalse($this->layanan()->kedaluwarsakan($p));

        $this->assertSame('kedaluwarsa', $p->fresh()->status);
        $this->assertSame($v->terpakai - 1, $v->fresh()->terpakai);
        $this->assertSame('pembayaran', Notifikasi::where('user_id', $user->id)->value('tipe'));
    }

    public function test_perintah_terjadwal_menyapu_semua_pesanan_lewat_batas(): void
    {
        $a = Pesanan::factory()->create(['kedaluwarsa_at' => now()->subMinute()]);
        $b = Pesanan::factory()->create(['kedaluwarsa_at' => now()->addHour()]);
        $c = Pesanan::factory()->lunas()->create(['kedaluwarsa_at' => now()->subDay()]);

        $this->artisan('undangyah:pesanan-kedaluwarsa')->expectsOutput('1 pesanan ditandai kedaluwarsa.')->assertSuccessful();

        $this->assertSame('kedaluwarsa', $a->fresh()->status);
        $this->assertSame('menunggu', $b->fresh()->status);
        $this->assertSame('lunas', $c->fresh()->status);
    }

    public function test_seeder_idempoten_mengisi_pengaturan_kuota_dan_tidak_menimpa_suntingan_admin(): void
    {
        Paket::where('kode', 'premium')->update(['harga' => 300000]);
        Voucher::where('kode', 'NIKAH20')->update(['aktif' => false]);

        $this->seed(PaketSeeder::class);
        $this->seed(PaketSeeder::class);

        $this->assertSame(3, Paket::count());
        $this->assertSame(8, AddOn::count());
        $this->assertSame(4, Voucher::count());
        $this->assertSame(300000, Paket::where('kode', 'premium')->value('harga'));
        $this->assertFalse(Voucher::where('kode', 'NIKAH20')->value('aktif'));
        $this->assertTrue((bool) Pengaturan::ambil('paket.aktif'));
        $this->assertSame(1, Pengaturan::ambil('paket.kuota.gratis'));
        $this->assertSame(3, Pengaturan::ambil('paket.kuota.premium'));
        $this->assertSame(10, Pengaturan::ambil('paket.kuota.deluxe'));
    }

    public function test_mengubah_kuota_paket_menyinkronkan_pengaturan(): void
    {
        Paket::where('kode', 'premium')->first()->update(['kuota_undangan' => 7, 'kuota_tamu' => 400]);

        $this->assertSame(7, Pengaturan::ambil('paket.kuota.premium'));
        $this->assertSame(400, Pengaturan::ambil('paket.kuota_tamu.premium'));
        $this->assertTrue((bool) Pengaturan::ambil('paket.aktif'), 'sakelar aktif tidak boleh terhapus');
    }

    public function test_metode_pembayaran_awal_memakai_rekening_placeholder_yang_jelas(): void
    {
        $m = MetodePembayaran::where('kode', 'transfer-bank')->first();

        $this->assertTrue($m->aktif);
        $this->assertTrue($m->data['placeholder']);
        $this->assertStringContainsString('GANTI DI ADMIN', $m->data['atas']);
        $this->assertFalse(MetodePembayaran::where('kode', 'dompet-digital')->value('aktif'));
    }
}
