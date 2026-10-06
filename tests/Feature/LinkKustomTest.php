<?php

namespace Tests\Feature;

use App\Livewire\LinkKustom;
use App\Models\Tamu;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class LinkKustomTest extends TestCase
{
    use RefreshDatabase;

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(LinkKustom::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    private function terbit(User $user, string $slug = 'raka-sinta', array $lain = []): Undangan
    {
        return Undangan::factory()->terbit()->create(array_merge(['user_id' => $user->id, 'slug' => $slug, 'judul' => 'Raka & Sinta'], $lain));
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/link-kustom')->assertRedirect(route('masuk'));
    }

    public function test_rute_dan_halaman_asli_bukan_placeholder(): void
    {
        $this->assertSame('/link-kustom', route('link-kustom', absolute: false));
        $u = $this->terbit($user = User::factory()->create());

        $this->actingAs($user)->get('/link-kustom')->assertOk()->assertSeeLivewire(LinkKustom::class);
        $this->actingAs($user)->get('/link-kustom/'.$u->id)->assertOk()->assertSeeLivewire(LinkKustom::class);
    }

    public function test_keadaan_kosong_tanpa_undangan(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada undangan')->assertSet('undanganId', null);
    }

    public function test_undangan_orang_lain_404_di_rute(): void
    {
        $orangLain = $this->terbit(User::factory()->create());

        $this->actingAs(User::factory()->create())->get('/link-kustom/'.$orangLain->id)->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/link-kustom/99999')->assertNotFound();
    }

    public function test_bawaan_memilih_undangan_terbit_milik_sendiri(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->create(['user_id' => $user->id, 'slug' => 'draf-baru']);
        $terbit = $this->terbit($user, 'yang-terbit', ['updated_at' => now()->subDay()]);
        $this->terbit(User::factory()->create(), 'punya-lain');

        $this->komponen($user)->assertSet('undanganId', $terbit->id)->assertSet('slug', 'yang-terbit');
    }

    public function test_alur_utama_ganti_slug_undangan_terbit(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, 'raka-sinta', ['terbitan' => ['judul' => 'x', 'slug' => 'raka-sinta']]);
        Tamu::factory()->create(['undangan_id' => $u->id, 'dikirim_at' => now()]);
        DB::table('rsvp')->insert(['undangan_id' => $u->id, 'nama' => 'A', 'status' => 'hadir', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ucapan')->insert(['undangan_id' => $u->id, 'nama' => 'A', 'pesan' => 'Selamat', 'created_at' => now(), 'updated_at' => now()]);

        $this->komponen($user, $u->id)
            ->assertSee('Link saat ini')
            ->set('slug', 'raka-dan-sinta-2027')
            ->assertSee('Link tersedia')
            ->call('bukaKonfirmasi')
            ->assertSet('tampilKonfirmasi', true)
            ->set('paham', true)
            ->call('ganti')
            ->assertHasNoErrors()
            ->assertSet('tampilKonfirmasi', false)
            ->assertSee('Link lama sudah tidak aktif');

        $u->refresh();
        $this->assertSame('raka-dan-sinta-2027', $u->slug);
        $this->assertSame('raka-dan-sinta-2027', $u->terbitan['slug']);
        $this->assertSame(Undangan::TERBIT, $u->status);
        $this->assertSame('raka-sinta', $u->pengaturan['riwayat_slug'][0]['dari']);
        $this->assertSame('raka-dan-sinta-2027', $u->pengaturan['riwayat_slug'][0]['ke']);
        $this->assertSame(1, $u->tamu()->count());
        $this->assertSame(1, $u->rsvp()->count());
        $this->assertSame(1, $u->ucapan()->count());
    }

    public function test_konfirmasi_wajib_dicentang_sebelum_slug_terbit_diganti(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $this->komponen($user, $u->id)->set('slug', 'alamat-baru')->call('bukaKonfirmasi')
            ->call('ganti')
            ->assertSet('tampilKonfirmasi', true)
            ->assertSee('Centang pernyataan');

        $this->assertSame('raka-sinta', $u->fresh()->slug);
    }

    public function test_slug_bentrok_dengan_undangan_lain_ditolak(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->terbit(User::factory()->create(), 'sudah-dipakai');

        $k = $this->komponen($user, $u->id)->set('slug', 'sudah-dipakai')
            ->assertSee('Alamat ini sudah dipakai undangan lain.')
            ->call('bukaKonfirmasi')->assertSet('tampilKonfirmasi', false);

        // Dipaksa lewat dialog pun tetap ditolak server.
        $k->set('paham', true)->call('ganti')->assertSee('Alamat ini sudah dipakai undangan lain.');
        $this->assertSame('raka-sinta', $u->fresh()->slug);
    }

    public function test_slug_terlarang_dan_format_salah_ditolak(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $k = $this->komponen($user, $u->id);
        foreach (['admin', 'login', 'template'] as $kata) {
            $k->set('slug', $kata)->assertSee('Alamat ini tidak tersedia. Pilih alamat lain.')->call('bukaKonfirmasi')->assertSet('tampilKonfirmasi', false);
        }
        $k->set('slug', 'ab')->assertSee('Alamat minimal 3 karakter.');
        $k->set('slug', str_repeat('a', 61))->assertSee('Alamat maksimal 60 karakter.');
        $k->set('slug', 'Halo Dunia!')->assertSet('slug', 'halo-dunia-')->assertSee('tanda hubung di awal/akhir');

        $k->set('slug', 'admin')->set('paham', true)->call('ganti')->assertSee('Alamat ini tidak tersedia');
        $this->assertSame('raka-sinta', $u->fresh()->slug);
    }

    public function test_slug_sama_dengan_sekarang_tidak_bisa_disimpan(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $this->komponen($user, $u->id)->assertSee('Ini link yang sedang dipakai.')
            ->call('bukaKonfirmasi')->assertSet('tampilKonfirmasi', false)
            ->set('paham', true)->call('ganti')->assertSee('sama dengan link sekarang');
    }

    public function test_undangan_draf_tidak_bisa_diganti_slugnya(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->create(['user_id' => $user->id, 'slug' => 'masih-draf']);

        $this->komponen($user, $u->id)
            ->assertSee('Terbitkan dulu untuk mengganti link')
            ->assertDontSee('Alamat baru')
            ->set('slug', 'alamat-lain')
            ->call('bukaKonfirmasi')->assertSet('tampilKonfirmasi', false)
            ->set('paham', true)->call('ganti')->assertSee('belum terbit');

        $this->assertSame('masih-draf', $u->fresh()->slug);
    }

    public function test_tidak_bisa_mengganti_slug_undangan_orang_lain(): void
    {
        $user = User::factory()->create();
        $milik = $this->terbit($user, 'milik-saya');
        $orangLain = $this->terbit(User::factory()->create(), 'punya-lain');

        $this->komponen($user)->call('pilih', $orangLain->id)->assertNotFound();

        $this->assertSame('punya-lain', $orangLain->fresh()->slug);
        $this->assertSame('milik-saya', $milik->fresh()->slug);
    }

    public function test_id_undangan_dikunci_dari_manipulasi_klien(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);
        $orangLain = $this->terbit(User::factory()->create(), 'punya-lain');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->set('undanganId', $orangLain->id);
    }

    public function test_beralih_antar_undangan_milik_sendiri_mereset_isian(): void
    {
        $user = User::factory()->create();
        $a = $this->terbit($user, 'satu-satu');
        $b = $this->terbit($user, 'dua-dua');

        $this->komponen($user, $a->id)->set('slug', 'ketikan-lain')->call('pilih', $b->id)
            ->assertSet('undanganId', $b->id)->assertSet('slug', 'dua-dua')->assertSet('paham', false);
    }

    public function test_kembalikan_mengisi_ulang_slug_sekarang(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $this->komponen($user, $u->id)->set('slug', 'apa-saja')->call('kembalikan')->assertSet('slug', 'raka-sinta');
    }

    public function test_saran_hanya_yang_tersedia_dan_bisa_dipakai(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, 'raka-sinta', ['tanggal_acara' => '2027-02-14']);
        $this->terbit(User::factory()->create(), 'raka-sinta-2027');

        $this->komponen($user, $u->id)
            ->assertSee('Saran link yang tersedia')
            ->assertSee('raka-sinta-menikah')
            ->assertDontSee('pakaiSaran(\'raka-sinta-2027\')', false)
            ->call('pakaiSaran', 'raka-sinta-menikah')
            ->assertSet('slug', 'raka-sinta-menikah')
            ->assertSee('Link tersedia');
    }

    public function test_pengecekan_ketersediaan_dibatasi_rate_limiter(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->terbit(User::factory()->create(), 'sudah-dipakai');
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit('link-kustom-cek:'.$user->id, 60);
        }

        $this->komponen($user, $u->id)->set('slug', 'sudah-dipakai')
            ->assertSee('Terlalu banyak pengecekan')
            ->assertDontSee('sudah dipakai undangan lain')
            ->call('bukaKonfirmasi')->assertSet('tampilKonfirmasi', false);
    }

    public function test_simpan_dibatasi_rate_limiter(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('link-kustom-simpan:'.$user->id, 60);
        }

        $this->komponen($user, $u->id)->set('slug', 'alamat-baru')->call('bukaKonfirmasi')->set('paham', true)
            ->call('ganti')->assertSee('Terlalu banyak percobaan');

        $this->assertSame('raka-sinta', $u->fresh()->slug);
    }

    public function test_riwayat_perubahan_tampil_dan_dibatasi(): void
    {
        $user = User::factory()->create();
        $riwayat = [];
        for ($i = 0; $i < 25; $i++) {
            $riwayat[] = ['dari' => "lama-$i", 'ke' => "baru-$i", 'pada' => now()->subDays($i)->toIso8601String()];
        }
        $u = $this->terbit($user, 'raka-sinta', ['pengaturan' => ['musik' => 'x', 'riwayat_slug' => $riwayat]]);

        $this->komponen($user, $u->id)->assertSee('lama-0')->assertDontSee('Belum ada perubahan link');

        $this->komponen($user, $u->id)->set('slug', 'alamat-baru')->call('bukaKonfirmasi')->set('paham', true)->call('ganti');
        $u->refresh();
        $this->assertCount(20, $u->pengaturan['riwayat_slug']);
        $this->assertSame('x', $u->pengaturan['musik']);
    }

    public function test_profil_menautkan_ke_link_kustom_bila_rute_ada(): void
    {
        $this->actingAs(User::factory()->create())->get('/akun')->assertOk()->assertSee(route('link-kustom'));
    }
}
