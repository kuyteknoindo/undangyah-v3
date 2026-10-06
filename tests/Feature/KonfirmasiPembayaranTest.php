<?php

namespace Tests\Feature;

use App\Livewire\KonfirmasiPembayaran;
use App\Models\ItemPesanan;
use App\Models\Notifikasi;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Keranjang;
use App\Services\KonfirmasiPesanan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class KonfirmasiPembayaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        Storage::fake('local');
    }

    private function pesanan(User $user, array $atribut = []): Pesanan
    {
        $p = Pesanan::factory()->create(['user_id' => $user->id, 'kode_unik' => 421, 'total' => 249421, 'metode_label' => 'Transfer manual',
            'metode_data' => ['tipe' => 'manual', 'bank' => 'BANK CONTOH', 'nomor' => '0000000000', 'atas' => 'A.N. Contoh', 'instruksi' => 'Silakan transfer.', 'langkah' => ['Buka mobile banking.', 'Unggah bukti.'], 'placeholder' => true]] + $atribut);
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => 'premium', 'nama' => 'Paket Premium', 'harga' => 249000, 'efek' => ['paket' => 'premium', 'durasi_bulan' => 12]]);

        return $p;
    }

    private function halaman(User $user, ?Pesanan $p)
    {
        return Livewire::actingAs($user)->test(KonfirmasiPembayaran::class, ['pesanan' => $p?->nomor]);
    }

    public function test_menampilkan_instruksi_transfer_nominal_dan_peringatan_placeholder(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);

        $this->actingAs($user)->get(route('konfirmasi-pembayaran', ['pesanan' => $p->nomor]))->assertOk()->assertSeeLivewire(KonfirmasiPembayaran::class);
        $this->halaman($user, $p)
            ->assertSee('Menunggu pembayaran')->assertSee('Nomor rekening BANK CONTOH')->assertSee('0000000000')->assertSee('Rp 249.421')
            ->assertSee('3 digit terakhir (421)')->assertSee('Buka mobile banking.')->assertSee('Jangan transfer sebelum admin memperbarui rekening')
            ->assertSee($p->nomor);
    }

    public function test_tamu_diarahkan_dan_pesanan_orang_lain_404(): void
    {
        $pemilik = User::factory()->create();
        $p = $this->pesanan($pemilik);

        $this->get(route('konfirmasi-pembayaran', ['pesanan' => $p->nomor]))->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get(route('konfirmasi-pembayaran', ['pesanan' => $p->nomor]))->assertNotFound();
        $this->actingAs($pemilik)->get('/konfirmasi-pembayaran/INV-2026-9999')->assertNotFound();
        $this->actingAs($pemilik)->get('/konfirmasi-pembayaran/apa-saja')->assertNotFound();
    }

    public function test_tanpa_parameter_menampilkan_pesanan_menunggu_terbaru_milik_sendiri(): void
    {
        $user = User::factory()->create();
        $lain = $this->pesanan(User::factory()->create());
        $lama = $this->pesanan($user, ['total' => 111111]);
        $baru = $this->pesanan($user, ['total' => 222222]);

        $this->halaman($user, null)->assertSee($baru->nomor)->assertDontSee($lama->nomor)->assertDontSee($lain->nomor);
        $this->halaman(User::factory()->create(), null)->assertSee('Tidak ada pesanan yang menunggu pembayaran');
    }

    public function test_id_pesanan_tidak_bisa_diganti_klien(): void
    {
        $user = User::factory()->create();
        $k = $this->halaman($user, $this->pesanan($user));
        $lain = $this->pesanan(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $k->set('pesananId', $lain->id);
    }

    public function test_unggah_bukti_valid_disimpan_privat_dengan_nama_acak(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);

        $this->halaman($user, $p)->set('bukti', UploadedFile::fake()->image('transfer saya.png', 300, 400))->assertHasNoErrors()->assertSee('Menunggu verifikasi admin')->assertSee('transfer saya.png');

        $p->refresh();
        $this->assertNotNull($p->bukti_path);
        $this->assertMatchesRegularExpression('#^bukti-pembayaran/[A-Za-z0-9]{40}\.png$#', $p->bukti_path);
        $this->assertNotNull($p->bukti_at);
        Storage::disk('local')->assertExists($p->bukti_path);
        $this->assertSame('menunggu', $p->status, 'unggah bukti tidak melunasi pesanan');
        $this->assertSame('gratis', $user->fresh()->paket);
    }

    public function test_unggah_bukti_memberi_notifikasi_ke_semua_admin_dengan_tautan_ke_data_pesanan(): void
    {
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();
        $user = User::factory()->create();
        $p = $this->pesanan($user);

        $this->halaman($user, $p)->set('bukti', UploadedFile::fake()->image('transfer.png'))->assertHasNoErrors();

        foreach ([$admin1, $admin2] as $a) {
            $n = Notifikasi::where('user_id', $a->id)->first();
            $this->assertNotNull($n);
            $this->assertSame('pembayaran', $n->tipe);
            $this->assertSame('Bukti pembayaran baru', $n->judul);
            $this->assertSame('/admin/pesanan?cari='.$p->nomor, $n->tautan);
            $this->assertStringContainsString($p->nomor, $n->isi);
        }
        $this->assertSame(0, Notifikasi::where('user_id', $user->id)->count(), 'pembeli tidak ikut diberi tahu');
        $this->actingAs($admin1)->get('/admin/pesanan?cari='.$p->nomor)->assertOk()->assertSee($p->nomor);
    }

    public function test_unggah_ditolak_tidak_memberi_notifikasi_admin_dan_bukti_baru_melepas_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $lunas = $this->pesanan($user, ['status' => 'lunas']);
        $this->halaman($user, $lunas)->set('bukti', UploadedFile::fake()->image('a.png'));
        $this->assertSame(0, Notifikasi::where('user_id', $admin->id)->count());

        $p = $this->pesanan($user, ['ditunda_at' => now()]);
        $this->halaman($user, $p)->set('bukti', UploadedFile::fake()->image('b.png'));
        $this->assertNull($p->fresh()->ditunda_at);
        $this->assertSame(1, Notifikasi::where('user_id', $admin->id)->count());
    }

    public function test_unggah_bukti_baru_mengganti_dan_menghapus_berkas_lama(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $k = $this->halaman($user, $p)->set('bukti', UploadedFile::fake()->image('a.jpg'));
        $lama = $p->fresh()->bukti_path;

        $k->set('bukti', UploadedFile::fake()->image('b.webp'));

        $baru = $p->fresh()->bukti_path;
        $this->assertNotSame($lama, $baru);
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($baru);
    }

    public function test_unggah_menolak_berkas_bukan_gambar_atau_terlalu_besar(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $k = $this->halaman($user, $p);

        $k->set('bukti', UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'))->assertHasErrors('bukti')->assertSee('Bukti harus berupa gambar JPG, PNG, atau WebP.');
        $k->set('bukti', UploadedFile::fake()->createWithContent('bukti.png', '<?php echo 1; ?>'))->assertHasErrors('bukti');
        $k->set('bukti', UploadedFile::fake()->createWithContent('bukti.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertHasErrors('bukti');
        $k->set('bukti', UploadedFile::fake()->image('besar.jpg')->size(3000))->assertHasErrors('bukti')->assertSee('Ukuran gambar maksimal 2 MB.');
        $k->set('bukti', UploadedFile::fake()->image('anim.gif'))->assertHasErrors('bukti');

        $this->assertNull($p->fresh()->bukti_path);
        $this->assertSame([], Storage::disk('local')->allFiles('bukti-pembayaran'));
    }

    public function test_unggah_pada_pesanan_lunas_atau_kedaluwarsa_ditolak(): void
    {
        $user = User::factory()->create();
        $lunas = $this->pesanan($user, ['status' => 'lunas', 'dibayar_at' => now()]);
        $kedaluwarsa = $this->pesanan($user, ['kedaluwarsa_at' => now()->subHour()]);

        $this->halaman($user, $lunas)->set('bukti', UploadedFile::fake()->image('a.png'));
        $this->halaman($user, $kedaluwarsa)->set('bukti', UploadedFile::fake()->image('a.png'));

        $this->assertNull($lunas->fresh()->bukti_path);
        $this->assertNull($kedaluwarsa->fresh()->bukti_path);
    }

    public function test_unggah_dibatasi_laju(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $k = $this->halaman($user, $p);
        for ($i = 0; $i < 10; $i++) {
            $k->set('bukti', UploadedFile::fake()->image("a$i.png"));
        }

        $k->set('bukti', UploadedFile::fake()->image('terakhir.png'))->assertHasErrors('bukti')->assertSee('Terlalu banyak unggahan');
        RateLimiter::clear('unggah-bukti:'.$user->id);
    }

    public function test_hapus_bukti(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $k = $this->halaman($user, $p)->set('bukti', UploadedFile::fake()->image('a.png'));
        $path = $p->fresh()->bukti_path;

        $k->call('hapusBukti');

        $this->assertNull($p->fresh()->bukti_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_bukti_hanya_ditayangkan_ke_pemilik_atau_admin(): void
    {
        $pemilik = User::factory()->create();
        $p = $this->pesanan($pemilik);
        $this->halaman($pemilik, $p)->set('bukti', UploadedFile::fake()->image('a.png'));
        $url = route('pesanan.bukti', $p);

        auth()->forgetGuards();
        $this->get($url)->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $r = $this->actingAs($pemilik)->get($url)->assertOk();
        $this->assertSame('inline', $r->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->actingAs($pemilik)->get(route('pesanan.bukti', $this->pesanan($pemilik)))->assertNotFound();
    }

    public function test_pengguna_membatalkan_pesanan_dan_voucher_kembali(): void
    {
        $user = User::factory()->create();
        $v = Voucher::where('kode', 'NIKAH20')->first();
        $p = $this->pesanan($user, ['voucher_id' => $v->id, 'voucher_kode' => 'NIKAH20', 'voucher_ditahan' => true]);

        $this->halaman($user, $p)->set('dialogBatal', true)->call('batalkan')->assertSet('dialogBatal', false)->assertSee('Pesanan dibatalkan');

        $this->assertSame('batal', $p->fresh()->status);
        $this->assertSame($v->terpakai - 1, $v->fresh()->terpakai);
    }

    public function test_pesanan_lunas_menampilkan_efek_dan_tidak_bisa_dibatalkan(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        app(KonfirmasiPesanan::class)->lunas($p);

        $this->halaman($user->fresh(), $p)->assertSee('Pembayaran diterima')->assertSee('Yang berubah di akunmu')->assertSee('Paket Premium aktif sampai')
            ->call('batalkan');

        $this->assertSame('lunas', $p->fresh()->status);
    }

    public function test_pesanan_kedaluwarsa_ditandai_saat_dibuka_dan_bisa_dipesan_ulang(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, ['kedaluwarsa_at' => now()->subMinute()]);
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'addon', 'kode' => 'kuota-undangan', 'nama' => 'Tambah kuota undangan', 'harga' => 25000]);

        $k = $this->halaman($user, $p)->assertSee('Waktu pembayaran habis')->assertSee('Buat pesanan baru');
        $this->assertSame('kedaluwarsa', $p->fresh()->status);

        $k->call('pesanUlang')->assertRedirect(route('checkout'));
        $this->assertSame(['paket' => 'premium', 'addons' => ['kuota-undangan']], app(Keranjang::class)->ambil());
    }

    public function test_pesanan_batal_menampilkan_status_dibatalkan(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, ['status' => 'batal']);

        $this->halaman($user, $p)->assertSee('Pesanan dibatalkan')->assertDontSee('Cara transfer');
    }

    public function test_bukti_ditolak_admin_menampilkan_alasan_dan_meminta_unggah_ulang(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $this->halaman($user, $p)->set('bukti', UploadedFile::fake()->image('a.png'));
        app(KonfirmasiPesanan::class)->tolak($p->fresh(), 'Nominal kurang');

        $this->halaman($user, $p)->assertSee('Bukti sebelumnya ditolak: Nominal kurang');
    }

    public function test_invoice_hanya_untuk_pemilik_atau_admin_dan_memuat_rincian(): void
    {
        $pemilik = User::factory()->create();
        $p = $this->pesanan($pemilik, ['pembeli_nama' => 'Raka <b>Pratama</b>', 'subtotal' => 249000, 'diskon' => 0]);
        $url = route('invoice', $p);

        $this->get($url)->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $r = $this->actingAs($pemilik)->get($url)->assertOk()
            ->assertSee('INVOICE '.$p->nomor)->assertSee('Paket Premium')->assertSee('Rp 249.421')->assertSee('Menunggu')
            ->assertSee('Raka &lt;b&gt;Pratama&lt;/b&gt;', false)->assertDontSee('Raka <b>Pratama</b>', false);
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->actingAs($pemilik)->get('/invoice/INV-2026-9999')->assertNotFound();
    }
}
