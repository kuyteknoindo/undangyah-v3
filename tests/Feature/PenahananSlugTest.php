<?php

namespace Tests\Feature;

use App\Livewire\Akun\InformasiAkun;
use App\Livewire\Beranda;
use App\Livewire\BuatUndangan;
use App\Livewire\EditorUndangan;
use App\Livewire\LinkKustom;
use App\Models\SlugDitahan;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use App\Services\PenahananSlug;
use App\Services\PenerbitUndangan;
use App\Services\PengelolaPengguna;
use App\Services\SlugUndangan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PenahananSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-06 10:00:00');
        RateLimiter::clear('link-kustom-simpan:1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function terbit(User $user, string $slug = 'raka-sinta', array $lain = []): Undangan
    {
        return Undangan::factory()->terbit()->create(array_merge(['user_id' => $user->id, 'slug' => $slug, 'judul' => 'Raka & Sinta'], $lain));
    }

    /** Ganti slug lewat Link Kustom, seperti pengguna sungguhan. */
    private function gantiLewatLinkKustom(User $user, Undangan $u, string $baru)
    {
        RateLimiter::clear('link-kustom-simpan:'.$user->id);

        return Livewire::actingAs($user)->test(LinkKustom::class, ['undangan' => (string) $u->id])->call('muat')
            ->set('slug', $baru)->call('bukaKonfirmasi')->set('paham', true)->call('ganti');
    }

    // ------------------------------------------------------------------ menahan

    public function test_ganti_slug_menahan_slug_lama_90_hari(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $this->gantiLewatLinkKustom($user, $u, 'raka-dan-sinta')->assertSet('galatKonfirmasi', '')->assertSee('dialihkan ke link baru selama 90 hari');

        $this->assertSame('raka-dan-sinta', $u->fresh()->slug);
        $h = SlugDitahan::where('slug', 'raka-sinta')->sole();
        $this->assertSame($user->id, $h->user_id);
        $this->assertSame($u->id, $h->undangan_id);
        $this->assertSame(SlugDitahan::GANTI, $h->alasan);
        $this->assertTrue($h->ditahan_sampai->equalTo(now()->addDays(90)));
        $this->assertFalse(SlugDitahan::where('slug', 'raka-dan-sinta')->exists());
    }

    public function test_hapus_undangan_menahan_slugnya(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        Livewire::actingAs($user)->test(Beranda::class)->call('konfirmasiHapus', $u->id)->call('hapus');

        $this->assertFalse(Undangan::whereKey($u->id)->exists());
        $h = SlugDitahan::where('slug', 'raka-sinta')->sole();
        $this->assertSame($user->id, $h->user_id);
        $this->assertNull($h->undangan_id);
        $this->assertSame(SlugDitahan::HAPUS, $h->alasan);
        $this->assertTrue($h->ditahan_sampai->equalTo(now()->addDays(90)));
    }

    public function test_hapus_akun_oleh_admin_dan_oleh_pemilik_tetap_menahan_slug(): void
    {
        // Lewat cascade basis data (tanpa event model Undangan).
        $a = User::factory()->create();
        $ua = $this->terbit($a, 'milik-a');
        $this->terbit($a, 'milik-a-dua');
        $a->delete();

        $this->assertFalse(Undangan::whereKey($ua->id)->exists());
        $this->assertSame(['milik-a', 'milik-a-dua'], SlugDitahan::orderBy('slug')->pluck('slug')->all());
        $this->assertSame([$a->id], SlugDitahan::pluck('user_id')->unique()->values()->all());
        $this->assertNull(SlugDitahan::where('slug', 'milik-a')->value('undangan_id'));

        $lain = User::factory()->create();
        $this->assertNotNull(app(SlugUndangan::class)->galat('milik-a', null, $lain->id));

        // Hapus akun mandiri oleh pemilik.
        $b = User::factory()->create(['password' => Hash::make('rahasia-123')]);
        $this->terbit($b, 'milik-b');
        Livewire::actingAs($b)->test(InformasiAkun::class)->set('teksHapus', 'HAPUS')->set('sandiHapus', 'rahasia-123')->call('hapusAkun');
        $this->assertTrue(SlugDitahan::where('slug', 'milik-b')->exists());
    }

    public function test_hapus_akun_lewat_pengelola_pengguna_admin_menahan_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['email' => 'target@contoh.com']);
        $this->terbit($target, 'milik-target');

        app(PengelolaPengguna::class)->hapus($admin, $target, 'target@contoh.com');

        $this->assertFalse(User::whereKey($target->id)->exists());
        $h = SlugDitahan::where('slug', 'milik-target')->sole();
        $this->assertSame($target->id, $h->user_id);
        $this->assertTrue($h->ditahan_sampai->equalTo(now()->addDays(90)));
    }

    public function test_draf_yang_slug_nya_diganti_tidak_ditahan(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->create(['user_id' => $user->id, 'slug' => 'ketik-sebagian']);

        $u->update(['slug' => 'ketik-sebagian-lagi']);

        $this->assertSame(0, SlugDitahan::count());
    }

    public function test_draf_yang_pernah_terbit_tetap_menahan_slug_saat_diganti(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, 'pernah-publik');
        $u->update(['status' => Undangan::DRAF, 'terbitan' => null]);
        $this->assertSame(0, SlugDitahan::count());

        $u->update(['slug' => 'slug-baru-draf']);

        $this->assertSame('pernah-publik', SlugDitahan::sole()->slug);
    }

    public function test_ganti_berulang_memperbarui_baris_bukan_menggandakan(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, 'satu-slug');

        $this->gantiLewatLinkKustom($user, $u, 'dua-slug');
        $this->assertSame(['satu-slug'], SlugDitahan::pluck('slug')->all());

        Carbon::setTestNow(now()->addDays(10));
        $this->gantiLewatLinkKustom($user, $u->fresh(), 'satu-slug'); // memakai lagi: penahanan dilepas, dua-slug ditahan
        $this->assertSame(['dua-slug'], SlugDitahan::pluck('slug')->all());

        $this->gantiLewatLinkKustom($user, $u->fresh(), 'dua-slug');
        $this->gantiLewatLinkKustom($user, $u->fresh(), 'satu-slug');
        $this->gantiLewatLinkKustom($user, $u->fresh(), 'dua-slug');
        $this->assertSame(1, SlugDitahan::where('slug', 'satu-slug')->count());
        $this->assertSame(1, SlugDitahan::count());
        $this->assertTrue(SlugDitahan::where('slug', 'satu-slug')->sole()->ditahan_sampai->equalTo(now()->addDays(90)));
        $this->assertSame('dua-slug', $u->fresh()->slug);

        // Menahan ulang slug yang sama langsung (tanpa dipakai dulu) memperbarui masa tahan.
        Carbon::setTestNow(now()->addDays(5));
        app(PenahananSlug::class)->tahan('satu-slug', $user->id, $u->id, SlugDitahan::GANTI);
        $this->assertSame(1, SlugDitahan::where('slug', 'satu-slug')->count());
        $this->assertTrue(SlugDitahan::where('slug', 'satu-slug')->sole()->ditahan_sampai->equalTo(now()->addDays(90)));
    }

    // ------------------------------------------------------------------ penolakan

    public function test_pengguna_lain_ditolak_di_link_kustom_dengan_pesan_tanpa_identitas(): void
    {
        $pemilik = User::factory()->create(['name' => 'Pemilik Rahasia', 'email' => 'pemilik-rahasia@contoh.com']);
        $this->gantiLewatLinkKustom($pemilik, $this->terbit($pemilik), 'raka-dan-sinta');

        $lain = User::factory()->create();
        $milikLain = $this->terbit($lain, 'slug-milik-lain');

        $k = Livewire::actingAs($lain)->test(LinkKustom::class, ['undangan' => (string) $milikLain->id])->call('muat')->set('slug', 'raka-sinta');
        $k->assertSee('ditahan sementara')->assertSee('4 Januari 2027')->assertDontSee('Pemilik Rahasia')->assertDontSee('pemilik-rahasia');
        $k->call('bukaKonfirmasi')->assertSet('tampilKonfirmasi', false);
        $k->set('paham', true)->call('ganti');
        $this->assertStringContainsString('ditahan sementara', (string) $k->get('galatKonfirmasi'));
        $this->assertSame('slug-milik-lain', $milikLain->fresh()->slug);
    }

    public function test_pesan_menyebut_tanggal_berakhir_dan_tak_membocorkan_pemilik(): void
    {
        $pemilik = User::factory()->create(['name' => 'Budi Pemilik']);
        app(PenahananSlug::class)->tahan('nama-ditahan', $pemilik->id, null, SlugDitahan::HAPUS);
        $lain = User::factory()->create();

        $pesan = app(SlugUndangan::class)->galat('nama-ditahan', null, $lain->id);

        $this->assertStringContainsString('ditahan sementara', $pesan);
        $this->assertStringContainsString('4 Januari 2027', $pesan);
        $this->assertStringNotContainsString('Budi', $pesan);
        $this->assertStringNotContainsString((string) $pemilik->id.'"', $pesan);
    }

    public function test_pengguna_lain_ditolak_di_wizard(): void
    {
        $pemilik = User::factory()->create();
        app(PenahananSlug::class)->tahan('raka-sinta', $pemilik->id, null, SlugDitahan::HAPUS);
        $tema = Tema::factory()->create();
        $lain = User::factory()->create();

        $k = Livewire::actingAs($lain)->test(BuatUndangan::class)->call('lanjut')->set('slug', 'raka-sinta');
        $k->assertHasErrors('slug')->assertSee('ditahan sementara');

        $k->set('pria', 'Raka')->set('wanita', 'Sinta')->set('tanggal', now()->addMonths(2)->toDateString());
        $k->set('slug', 'raka-sinta')->call('lanjut')->assertHasErrors('slug')->assertSet('langkah', 2);
        $this->assertSame(0, Undangan::count());

        $this->assertNotNull($tema);
    }

    public function test_wizard_tidak_menyarankan_slug_yang_ditahan_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        app(PenahananSlug::class)->tahan('raka-sinta', $pemilik->id, null, SlugDitahan::HAPUS);
        $lain = User::factory()->create();
        Tema::factory()->create();

        $k = Livewire::actingAs($lain)->test(BuatUndangan::class)->call('lanjut')
            ->set('pria', 'Raka')->set('wanita', 'Sinta')->set('tanggal', now()->addMonths(2)->toDateString());

        $this->assertNotSame('raka-sinta', $k->get('slug'));
        $k->assertHasNoErrors('slug');
    }

    public function test_pengguna_lain_ditolak_di_terbit_ulang_dan_editor_draf(): void
    {
        $pemilik = User::factory()->create();
        app(PenahananSlug::class)->tahan('slug-incaran', $pemilik->id, null, SlugDitahan::HAPUS);
        $lain = User::factory()->create();
        $tema = Tema::factory()->create();

        // Realtime di dialog Terbitkan (editor).
        $u = Undangan::factory()->create(['user_id' => $lain->id, 'tema_id' => $tema->id, 'slug' => 'slug-draf-lain']);
        $e = Livewire::actingAs($lain)->test(EditorUndangan::class, ['undangan' => $u->id]);
        $e->set('slugTerbit', 'slug-incaran');
        $this->assertStringContainsString('ditahan sementara', (string) $e->get('galatSlug'));

        // Alamat draf di editor.
        $e->set('slug', 'slug-incaran');
        $this->assertStringContainsString('ditahan sementara', (string) $e->get('galatSlug'));
        $this->assertSame('slug-draf-lain', $u->fresh()->slug);

        // Penerbit (server): ValidationException.
        try {
            app(PenerbitUndangan::class)->terbitkan($u->fresh(), 'slug-incaran');
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $ex) {
            $this->assertStringContainsString('ditahan sementara', $ex->errors()['slug'][0]);
        }
        $this->assertSame('slug-draf-lain', $u->fresh()->slug);
    }

    public function test_penyimpanan_langsung_juga_ditolak_dan_transaksi_dibatalkan(): void
    {
        $pemilik = User::factory()->create();
        app(PenahananSlug::class)->tahan('slug-incaran', $pemilik->id, null, SlugDitahan::HAPUS);
        $lain = User::factory()->create();
        $u = Undangan::factory()->terbit()->create(['user_id' => $lain->id, 'slug' => 'slug-awal']);

        try {
            $u->update(['slug' => 'slug-incaran']);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException) {
        }
        $this->assertSame('slug-awal', $u->fresh()->slug);

        try {
            Undangan::factory()->create(['user_id' => $lain->id, 'slug' => 'slug-incaran']);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException) {
        }
        $this->assertFalse(Undangan::where('slug', 'slug-incaran')->exists());
        $this->assertSame(1, SlugDitahan::count());
    }

    public function test_slug_terlarang_tetap_ditolak(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, 'slug-biasa');

        $this->assertStringContainsString('tidak tersedia', (string) app(SlugUndangan::class)->galat('admin', null, $user->id));
        $this->gantiLewatLinkKustom($user, $u, 'admin')->assertSee('tidak tersedia');
        $this->assertSame('slug-biasa', $u->fresh()->slug);
        $this->assertSame(0, SlugDitahan::count());
    }

    // ------------------------------------------------------------------ pemilik lama

    public function test_pemilik_lama_boleh_memakai_kembali_dan_penahanan_dihapus(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->gantiLewatLinkKustom($user, $u, 'raka-dan-sinta');
        $this->assertTrue(SlugDitahan::where('slug', 'raka-sinta')->exists());

        $k = Livewire::actingAs($user)->test(LinkKustom::class, ['undangan' => (string) $u->id])->call('muat')->set('slug', 'raka-sinta');
        $k->assertDontSee('ditahan sementara');
        $k->call('bukaKonfirmasi')->assertSet('tampilKonfirmasi', true)->set('paham', true)->call('ganti');

        $this->assertSame('raka-sinta', $u->fresh()->slug);
        $this->assertFalse(SlugDitahan::where('slug', 'raka-sinta')->exists());
        // Slug yang baru ditinggalkan gantian ditahan.
        $this->assertTrue(SlugDitahan::where('slug', 'raka-dan-sinta')->exists());
    }

    public function test_pemilik_lama_memakai_slug_tahanan_hapus_di_undangan_baru(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $u->delete();
        $this->assertTrue(SlugDitahan::where('slug', 'raka-sinta')->exists());

        $baru = Undangan::factory()->create(['user_id' => $user->id, 'slug' => 'raka-sinta']);

        $this->assertSame('raka-sinta', $baru->slug);
        $this->assertFalse(SlugDitahan::where('slug', 'raka-sinta')->exists());
    }

    // ------------------------------------------------------------------ kedaluwarsa

    public function test_kedaluwarsa_tepat_setelah_90_hari(): void
    {
        $pemilik = User::factory()->create();
        $lain = User::factory()->create();
        app(PenahananSlug::class)->tahan('raka-sinta', $pemilik->id, null, SlugDitahan::HAPUS);
        $layanan = app(SlugUndangan::class);

        Carbon::setTestNow(now()->addDays(90)->subSecond());
        $this->assertStringContainsString('ditahan sementara', (string) $layanan->galat('raka-sinta', null, $lain->id));

        Carbon::setTestNow(now()->addSecond()); // tepat 90 hari
        $this->assertNull($layanan->galat('raka-sinta', null, $lain->id));
        $this->assertSame('raka-sinta', Undangan::factory()->create(['user_id' => $lain->id, 'slug' => 'raka-sinta'])->slug);
    }

    public function test_pembersihan_hanya_menghapus_yang_kedaluwarsa_dan_idempoten(): void
    {
        $user = User::factory()->create();
        $p = app(PenahananSlug::class);
        $p->tahan('masih-ditahan', $user->id, null, SlugDitahan::HAPUS);
        $p->tahan('sudah-lewat', $user->id, null, SlugDitahan::GANTI);
        SlugDitahan::where('slug', 'sudah-lewat')->update(['ditahan_sampai' => now()->subSecond()]);
        $p->tahan('tepat-sekarang', $user->id, null, SlugDitahan::GANTI);
        SlugDitahan::where('slug', 'tepat-sekarang')->update(['ditahan_sampai' => now()]);

        $this->artisan('undangyah:slug-bersih')->expectsOutput('2 penahanan slug kedaluwarsa dihapus.')->assertSuccessful();
        $this->assertSame(['masih-ditahan'], SlugDitahan::pluck('slug')->all());

        $this->artisan('undangyah:slug-bersih')->expectsOutput('0 penahanan slug kedaluwarsa dihapus.')->assertSuccessful();
        $this->assertSame(1, SlugDitahan::count());
    }

    public function test_pembersihan_terjadwal_harian(): void
    {
        $acara = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'undangyah:slug-bersih'));

        $this->assertNotNull($acara);
        $this->assertSame('0 0 * * *', $acara->expression);
    }

    // ------------------------------------------------------------------ alihan publik

    public function test_slug_lama_dialihkan_301_ke_slug_baru_dengan_query_terjaga(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->gantiLewatLinkKustom($user, $u, 'raka-dan-sinta');

        $this->get('/raka-sinta')->assertStatus(301)->assertRedirect('/raka-dan-sinta');
        $r = $this->get('/raka-sinta?to=ABC1234567&utm=x');
        $r->assertStatus(301);
        $this->assertSame('/raka-dan-sinta?to=ABC1234567&utm=x', parse_url($r->headers->get('Location'), PHP_URL_PATH).'?'.parse_url($r->headers->get('Location'), PHP_URL_QUERY));
        $this->assertSame(parse_url(url('/'), PHP_URL_HOST), parse_url($r->headers->get('Location'), PHP_URL_HOST));
    }

    public function test_ganti_dua_kali_alihan_langsung_ke_slug_terkini(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, 'versi-satu');
        $this->gantiLewatLinkKustom($user, $u, 'versi-dua');
        $this->gantiLewatLinkKustom($user, $u->fresh(), 'versi-tiga');

        $this->get('/versi-satu')->assertStatus(301)->assertRedirect('/versi-tiga');
        $this->get('/versi-dua')->assertStatus(301)->assertRedirect('/versi-tiga');
    }

    public function test_tidak_dialihkan_bila_tujuan_draf_atau_dihapus_atau_kedaluwarsa(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->gantiLewatLinkKustom($user, $u, 'raka-dan-sinta');
        $this->get('/raka-sinta')->assertStatus(301);

        // Tujuan jadi draf: 404 (draf tidak bocor).
        $u->fresh()->update(['status' => Undangan::DRAF, 'terbitan' => null]);
        $this->get('/raka-sinta')->assertNotFound();
        $u->fresh()->update(['status' => Undangan::TERBIT, 'terbitan' => ['judul' => 'x']]);
        $this->get('/raka-sinta')->assertStatus(301);

        // Kedaluwarsa: 404 biasa.
        Carbon::setTestNow(now()->addDays(91));
        $this->get('/raka-sinta')->assertNotFound();
        Carbon::setTestNow('2026-10-06 10:00:00');
        $this->get('/raka-sinta')->assertStatus(301);

        // Undangan dihapus: 404, slug lama dan slug terakhir tetap ditahan.
        $u->fresh()->delete();
        $this->get('/raka-sinta')->assertNotFound();
        $this->get('/raka-dan-sinta')->assertNotFound();
        $this->assertTrue(SlugDitahan::where('slug', 'raka-sinta')->aktif()->exists());
        $this->assertTrue(SlugDitahan::where('slug', 'raka-dan-sinta')->aktif()->exists());
    }

    public function test_slug_hasil_hapus_tanpa_pengalihan_adalah_404_dan_rsvp_ucapan_slug_lama_404(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->gantiLewatLinkKustom($user, $u, 'raka-dan-sinta');

        $this->get('/raka-sinta/ucapan')->assertNotFound();
        $this->postJson('/raka-sinta/rsvp', ['nama' => 'Budi', 'status' => 'hadir', 'jumlah' => 1])->assertNotFound();
        $this->postJson('/raka-sinta/ucapan', ['nama' => 'Budi', 'pesan' => 'Selamat'])->assertNotFound();
        $this->get('/slug-tak-dikenal')->assertNotFound();
    }

    public function test_slug_yang_dipakai_undangan_aktif_menang_atas_pengalihan(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->gantiLewatLinkKustom($user, $u, 'raka-dan-sinta');
        // Pemilik lama memakai lagi slug itu di undangan lain: penahanan lepas, halaman tampil biasa (bukan alihan).
        $lain = $this->terbit($user, 'raka-sinta');

        $this->assertFalse(SlugDitahan::where('slug', 'raka-sinta')->exists());
        $this->assertSame('raka-sinta', $lain->fresh()->slug);
        $this->assertNotSame(301, $this->get('/raka-sinta')->getStatusCode());
    }
}
