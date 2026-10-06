<?php

namespace Tests\Feature;

use App\Jobs\KirimSiaranNotifikasi;
use App\Livewire\Admin\Notifikasi as HalamanAdmin;
use App\Models\Notifikasi;
use App\Models\SiaranNotifikasi;
use App\Models\User;
use App\Services\PengirimSiaran;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Bos Admin']);
    }

    private function halaman(?User $sebagai = null)
    {
        return Livewire::actingAs($sebagai ?? $this->admin)->test(HalamanAdmin::class);
    }

    private function isi($h, array $nilai = [])
    {
        return $h->set('judul', $nilai['judul'] ?? 'Tema baru tersedia')->set('isi', $nilai['isi'] ?? 'Kami menambahkan tema baru untuk undanganmu.')
            ->set('tautan', $nilai['tautan'] ?? '')->set('paket', $nilai['paket'] ?? 'semua')->set('peran', $nilai['peran'] ?? 'semua');
    }

    // ---- akses ----

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/admin/notifikasi')->assertRedirect(route('masuk'));
    }

    public function test_non_admin_ditolak_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/notifikasi')->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(HalamanAdmin::class)->assertForbidden();
    }

    public function test_admin_melihat_halaman_asli(): void
    {
        $this->actingAs($this->admin)->get('/admin/notifikasi')->assertOk()->assertSeeLivewire(HalamanAdmin::class)->assertSee('Siaran baru')->assertSee('Belum ada siaran');
    }

    public function test_aksi_kirim_memeriksa_ulang_admin(): void
    {
        $komponen = $this->isi($this->halaman());
        $this->actingAs(User::factory()->create());

        $komponen->call('kirim')->assertForbidden();
        $this->assertSame(0, SiaranNotifikasi::count());
    }

    // ---- penerima ----

    public function test_jumlah_penerima_menurut_paket_peran_dan_status_akun(): void
    {
        User::factory()->count(2)->create();                                                                                   // gratis
        User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->addMonth()]);                                  // premium aktif
        User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->subDay()]);                                    // premium habis => gratis
        User::factory()->unverified()->create();                                                                               // dikecualikan
        User::factory()->create(['ditangguhkan_at' => now()]);                                                                 // dikecualikan
        // total terverifikasi & aktif: admin + 2 + 1 + 1 = 5
        $service = app(PengirimSiaran::class);

        $this->assertSame(5, $service->hitungPenerima('semua', 'semua'));
        $this->assertSame(4, $service->hitungPenerima('gratis', 'semua')); // 2 gratis + 1 premium habis + admin
        $this->assertSame(1, $service->hitungPenerima('premium', 'semua'));
        $this->assertSame(1, $service->hitungPenerima('semua', 'admin'));
        $this->assertSame(4, $service->hitungPenerima('semua', 'user'));
        $this->assertSame(0, $service->hitungPenerima('premium', 'admin'));

        $h = $this->halaman()->set('paket', 'premium');
        $h->assertSee('pengguna akan menerima siaran ini');
        $this->assertStringContainsString('>1</span> pengguna akan menerima', preg_replace('/\s+/', ' ', $h->html()));
    }

    // ---- validasi ----

    public function test_validasi_judul_isi_dan_sasaran(): void
    {
        $this->halaman()->set('judul', 'ab')->set('isi', 'pendek')->call('mintaKirim')->assertHasErrors(['judul' => 'min', 'isi' => 'min']);
        $this->isi($this->halaman(), ['judul' => str_repeat('x', 61)])->call('mintaKirim')->assertHasErrors(['judul' => 'max']);
        $this->isi($this->halaman(), ['isi' => str_repeat('x', 301)])->call('mintaKirim')->assertHasErrors(['isi' => 'max']);
        $this->isi($this->halaman(), ['paket' => 'platinum'])->call('mintaKirim')->assertHasErrors('paket');
        $this->isi($this->halaman(), ['peran' => 'root'])->call('mintaKirim')->assertHasErrors('peran');
        $this->halaman()->set('judul', '   ')->set('isi', "\n\n")->call('mintaKirim')->assertHasErrors(['judul', 'isi']);
    }

    public function test_tautan_berbahaya_ditolak_dan_tautan_aman_diterima(): void
    {
        foreach (['javascript:alert(1)', '//jahat.test/x', 'data:text/html,hai', 'ftp://x.test', 'billing', 'JaVaScRiPt:alert(1)'] as $buruk) {
            $this->isi($this->halaman(), ['tautan' => $buruk])->call('mintaKirim')->assertHasErrors('tautan');
        }
        foreach (['/billing', 'https://undangyah.com/promo', 'http://contoh.test'] as $baik) {
            $this->isi($this->halaman(), ['tautan' => $baik])->call('mintaKirim')->assertHasNoErrors()->assertSet('tampilKirim', true);
        }
    }

    public function test_tidak_ada_penerima_tidak_bisa_dikirim(): void
    {
        $h = $this->isi($this->halaman(), ['peran' => 'user'])->call('mintaKirim');

        $h->assertHasErrors('paket')->assertSet('tampilKirim', false);
    }

    // ---- kirim ----

    public function test_kirim_siaran_membuat_notifikasi_untuk_penerima_saja(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->addMonth()]);
        $belum = User::factory()->unverified()->create();
        $blok = User::factory()->create(['ditangguhkan_at' => now()]);

        $this->isi($this->halaman(), ['tautan' => '/billing', 'paket' => 'semua', 'peran' => 'user'])
            ->call('mintaKirim')->assertSet('tampilKirim', true)
            ->call('kirim')->assertHasNoErrors()->assertSet('tampilKirim', false)->assertSet('judul', '')->assertDispatched('toast');

        $siaran = SiaranNotifikasi::firstOrFail();
        $this->assertSame('selesai', $siaran->status);
        $this->assertSame(2, $siaran->terkirim);
        $this->assertSame(2, $siaran->jumlah_target);
        $this->assertSame($this->admin->id, $siaran->admin_id);

        foreach ([$a, $b] as $u) {
            $n = Notifikasi::where('user_id', $u->id)->firstOrFail();
            $this->assertSame('Tema baru tersedia', $n->judul);
            $this->assertSame('/billing', $n->tautan);
            $this->assertSame('sistem', $n->tipe);
            $this->assertSame($siaran->id, $n->data['siaran_id']);
        }
        $this->assertSame(0, Notifikasi::where('user_id', $belum->id)->count());
        $this->assertSame(0, Notifikasi::where('user_id', $blok->id)->count());
        $this->assertSame(0, Notifikasi::where('user_id', $this->admin->id)->count());
    }

    public function test_kirim_ke_segmen_paket(): void
    {
        $gratis = User::factory()->create();
        $premium = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->addMonth()]);

        $this->isi($this->halaman(), ['paket' => 'premium', 'peran' => 'user'])->call('kirim')->assertHasNoErrors();

        $this->assertSame(1, Notifikasi::where('user_id', $premium->id)->count());
        $this->assertSame(0, Notifikasi::where('user_id', $gratis->id)->count());
    }

    public function test_pengiriman_per_potongan_tanpa_duplikat_dan_melanjutkan(): void
    {
        Queue::fake();
        User::factory()->count(5)->create();
        $siaran = app(PengirimSiaran::class)->buat($this->admin, ['tipe' => 'sistem', 'judul' => 'Halo semua', 'isi' => 'Ini pengumuman penting.', 'tautan' => null, 'paket' => 'semua', 'peran' => 'semua']);
        Queue::assertPushed(KirimSiaranNotifikasi::class, fn ($j) => $j->siaranId === $siaran->id);
        $this->assertSame('antre', $siaran->status);
        $this->assertSame(6, $siaran->jumlah_target);

        $pengirim = app(PengirimSiaran::class);
        $this->assertFalse($pengirim->proses($siaran->id, 4));
        $this->assertSame(['berjalan', 4], [$siaran->fresh()->status, $siaran->fresh()->terkirim]);
        $this->assertSame(4, Notifikasi::count());

        $this->assertTrue($pengirim->proses($siaran->id, 4));
        $this->assertSame(['selesai', 6], [$siaran->fresh()->status, $siaran->fresh()->terkirim]);
        $this->assertSame(6, Notifikasi::count());
        $this->assertSame(6, Notifikasi::distinct('user_id')->count('user_id'));

        // Diproses ulang setelah selesai tidak menggandakan apa pun.
        $this->assertTrue($pengirim->proses($siaran->id, 4));
        $this->assertSame(6, Notifikasi::count());
    }

    public function test_job_mengantrekan_dirinya_lagi_sampai_selesai(): void
    {
        User::factory()->count(3)->create();
        Queue::fake();
        $siaran = app(PengirimSiaran::class)->buat($this->admin, ['tipe' => 'sistem', 'judul' => 'Halo semua', 'isi' => 'Ini pengumuman penting.', 'tautan' => null, 'paket' => 'semua', 'peran' => 'semua']);

        // Satu potongan 500 sudah mencakup semua: sekali jalan selesai dan tidak dijadwalkan ulang.
        (new KirimSiaranNotifikasi($siaran->id))->handle(app(PengirimSiaran::class));
        Queue::assertPushed(KirimSiaranNotifikasi::class, 1);
        $this->assertSame('selesai', $siaran->fresh()->status);
    }

    public function test_job_gagal_menandai_siaran_gagal(): void
    {
        Queue::fake();
        $siaran = app(PengirimSiaran::class)->buat($this->admin, ['tipe' => 'sistem', 'judul' => 'Halo semua', 'isi' => 'Ini pengumuman penting.', 'tautan' => null, 'paket' => 'semua', 'peran' => 'semua']);

        (new KirimSiaranNotifikasi($siaran->id))->failed(new \RuntimeException('boom'));

        $this->assertSame('gagal', $siaran->fresh()->status);
    }

    public function test_tipe_di_luar_daftar_menjadi_sistem_dan_tautan_asing_dibuang_oleh_layanan(): void
    {
        Queue::fake();
        $s = app(PengirimSiaran::class)->buat($this->admin, ['tipe' => 'rsvp', 'judul' => 'Halo semua', 'isi' => 'Ini pengumuman penting.', 'tautan' => 'javascript:alert(1)', 'paket' => 'semua', 'peran' => 'semua']);

        $this->assertSame('sistem', $s->tipe);
        $this->assertNull($s->tautan);
    }

    public function test_layanan_menolak_pembuat_non_admin(): void
    {
        $this->expectException(HttpException::class);
        app(PengirimSiaran::class)->buat(User::factory()->create(), ['tipe' => 'sistem', 'judul' => 'Halo semua', 'isi' => 'Ini pengumuman penting.', 'tautan' => null, 'paket' => 'semua', 'peran' => 'semua']);
    }

    // ---- batas ----

    public function test_dibatasi_laju_per_admin(): void
    {
        User::factory()->create();
        foreach (range(1, HalamanAdmin::MAKS_SIARAN_PER_JAM) as $_) {
            RateLimiter::hit('siaran-notifikasi:'.$this->admin->id, 3600);
        }

        $this->isi($this->halaman())->call('kirim')->assertDispatched('toast', tipe: 'galat');
        $this->assertSame(0, SiaranNotifikasi::count());
    }

    public function test_siaran_kembar_dalam_lima_menit_ditolak(): void
    {
        User::factory()->create();

        $h = $this->isi($this->halaman())->call('kirim');
        $this->isi($h)->call('kirim')->assertDispatched('toast', tipe: 'galat');

        $this->assertSame(1, SiaranNotifikasi::count());
        $this->assertSame(2, Notifikasi::where('judul', 'Tema baru tersedia')->count()); // satu per penerima (pengguna + admin), tidak digandakan
    }

    // ---- riwayat ----

    public function test_riwayat_cari_filter_dan_gunakan_lagi(): void
    {
        SiaranNotifikasi::create(['admin_id' => $this->admin->id, 'tipe' => 'sistem', 'judul' => 'Promo Lebaran', 'isi' => 'Diskon besar sekali.', 'target_paket' => 'premium', 'target_peran' => 'user', 'jumlah_target' => 10, 'terkirim' => 10, 'status' => 'selesai']);
        $lain = SiaranNotifikasi::create(['admin_id' => $this->admin->id, 'tipe' => 'sistem', 'judul' => 'Pemeliharaan', 'isi' => 'Server dirawat malam ini.', 'target_paket' => 'semua', 'target_peran' => 'semua', 'jumlah_target' => 4, 'terkirim' => 1, 'status' => 'berjalan']);

        $this->halaman()->assertSee('Promo Lebaran')->assertSee('Pemeliharaan')
            ->set('q', 'lebaran')->assertSee('Promo Lebaran')->assertDontSee('Pemeliharaan')
            ->set('q', '')->call('pilihFilter', 'berjalan')->assertSee('Pemeliharaan')->assertDontSee('Promo Lebaran')
            ->call('pilihFilter', 'ngawur')->assertSet('filter', 'semua')
            ->call('gunakanLagi', $lain->id)->assertSet('judul', 'Pemeliharaan')->assertSet('isi', 'Server dirawat malam ini.');
    }

    public function test_proses_sekarang_menuntaskan_siaran_yang_menunggu(): void
    {
        Queue::fake();
        User::factory()->count(2)->create();
        $siaran = app(PengirimSiaran::class)->buat($this->admin, ['tipe' => 'sistem', 'judul' => 'Halo semua', 'isi' => 'Ini pengumuman penting.', 'tautan' => null, 'paket' => 'semua', 'peran' => 'semua']);

        $this->halaman()->assertSee('Proses sekarang')->call('prosesSekarang', $siaran->id)->assertDispatched('toast', tipe: 'sukses');

        $this->assertSame('selesai', $siaran->fresh()->status);
        $this->assertSame(3, Notifikasi::count());
    }

    public function test_proses_sekarang_ditolak_untuk_non_admin(): void
    {
        $siaran = SiaranNotifikasi::create(['tipe' => 'sistem', 'judul' => 'Halo semua', 'isi' => 'Ini pengumuman penting.', 'status' => 'antre']);
        $komponen = $this->halaman();
        $this->actingAs(User::factory()->create());

        $komponen->call('prosesSekarang', $siaran->id)->assertForbidden();
        $this->assertSame('antre', $siaran->fresh()->status);
    }

    public function test_pratinjau_tidak_merender_html_mentah(): void
    {
        $h = $this->halaman()->set('judul', '<script>alert(1)</script>')->set('isi', '<img src=x onerror=alert(1)> halo');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $h->html());
        $this->assertStringNotContainsString('<img src=x', $h->html());
    }
}
