<?php

namespace Tests\Feature;

use App\Livewire\TemplateDesain;
use App\Models\Pengaturan;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateDesainTest extends TestCase
{
    use RefreshDatabase;

    private function komponen(User $user, array $query = [])
    {
        return Livewire::actingAs($user)->withQueryParams($query)->test(TemplateDesain::class)->call('muat');
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/template')->assertRedirect(route('masuk'));
    }

    public function test_rute_menampilkan_halaman_asli_bukan_placeholder(): void
    {
        $this->assertSame('/template', route('template', absolute: false));

        $this->actingAs(User::factory()->create())->get('/template')
            ->assertOk()
            ->assertSeeLivewire(TemplateDesain::class);
    }

    public function test_katalog_hanya_memuat_tema_terbit_yang_tidak_terhapus(): void
    {
        Tema::factory()->create(['nama' => 'Tema Terbit']);
        Tema::factory()->draft()->create(['nama' => 'Tema Draf']);
        Tema::factory()->create(['nama' => 'Tema Dihapus'])->delete();

        $this->komponen(User::factory()->create())
            ->assertSee('Tema Terbit')
            ->assertDontSee('Tema Draf')
            ->assertDontSee('Tema Dihapus')
            ->assertSee('1 dari 1 tema');
    }

    public function test_keadaan_kosong_bila_belum_ada_tema(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada tema yang diterbitkan');
    }

    public function test_pencarian_dan_filter_paket(): void
    {
        Tema::factory()->create(['nama' => 'Mawar Merah', 'tier' => 'Gratis', 'font_judul' => 'playfair']);
        Tema::factory()->create(['nama' => 'Langit Biru', 'tier' => 'Premium', 'font_judul' => 'script']);
        Tema::factory()->create(['nama' => 'Emas Mewah', 'tier' => 'Deluxe', 'font_judul' => 'jakarta']);

        $k = $this->komponen(User::factory()->create());
        $k->set('q', 'mawar')->assertSee('Mawar Merah')->assertDontSee('Langit Biru')->assertSee('1 dari 3 tema');
        $k->set('q', 'sans modern')->assertSee('Emas Mewah')->assertDontSee('Mawar Merah');
        $k->set('q', '')->call('pilihTier', 'Premium')->assertSee('Langit Biru')->assertDontSee('Mawar Merah');
        $k->set('q', 'tidak-ada-yang-cocok')->assertSee('Tema tidak ditemukan')
            ->call('tampilkanSemua')->assertSet('q', '')->assertSet('tier', 'Semua')->assertSee('Mawar Merah');
    }

    public function test_tier_tidak_dikenal_kembali_ke_semua(): void
    {
        $this->komponen(User::factory()->create())->call('pilihTier', 'Platinum')->assertSet('tier', 'Semua');
    }

    public function test_pakai_tema_ke_draf_tidak_menimpa_isi_pengguna(): void
    {
        $user = User::factory()->create();
        $lama = Tema::factory()->create(['nama' => 'Lama']);
        $baru = Tema::factory()->create(['nama' => 'Baru']);
        $u = Undangan::factory()->create([
            'user_id' => $user->id, 'tema_id' => $lama->id, 'slug' => 'raka-sinta',
            'isi' => ['priaP' => 'Raka', 'hmJam' => '09:00'], 'bagian' => ['urutan' => ['sampul'], 'sembunyi' => []],
            'langkah' => ['tema' => false, 'data' => true],
        ]);

        $this->komponen($user)
            ->assertSet('undanganId', $u->id)
            ->call('pakai', $baru->id)
            ->assertSee('Tema Baru dipakai');

        $u->refresh();
        $this->assertSame($baru->id, $u->tema_id);
        $this->assertFalse($u->ada_perubahan);
        $this->assertSame(['priaP' => 'Raka', 'hmJam' => '09:00'], $u->isi);
        $this->assertSame(['urutan' => ['sampul'], 'sembunyi' => []], $u->bagian);
        $this->assertSame('raka-sinta', $u->slug);
        $this->assertSame(Undangan::DRAF, $u->status);
        $this->assertTrue($u->langkah['tema']);
        $this->assertTrue($u->langkah['data']);
    }

    public function test_pakai_tema_pada_undangan_terbit_menandai_ada_perubahan(): void
    {
        $user = User::factory()->create();
        $lama = Tema::factory()->create();
        $baru = Tema::factory()->create();
        $u = Undangan::factory()->terbit()->create(['user_id' => $user->id, 'tema_id' => $lama->id, 'isi' => ['x' => 'y'], 'terbitan' => ['judul' => 'beku']]);

        $this->komponen($user)->call('pakai', $baru->id)->assertSee('menerbitkan ulang');

        $u->refresh();
        $this->assertSame($baru->id, $u->tema_id);
        $this->assertTrue($u->ada_perubahan);
        $this->assertSame(Undangan::TERBIT, $u->status);
        $this->assertSame(['judul' => 'beku'], $u->terbitan);
        $this->assertSame(['x' => 'y'], $u->isi);
    }

    public function test_memilih_undangan_target_lain_milik_sendiri(): void
    {
        $user = User::factory()->create();
        $tema = Tema::factory()->create();
        $a = Undangan::factory()->create(['user_id' => $user->id]);
        $b = Undangan::factory()->create(['user_id' => $user->id]);

        $this->komponen($user)->call('pilihUndangan', $a->id)->assertSet('undanganId', $a->id)
            ->call('pakai', $tema->id);

        $this->assertSame($tema->id, $a->fresh()->tema_id);
        $this->assertNull($b->fresh()->tema_id);
    }

    public function test_undangan_orang_lain_tidak_bisa_dipilih_atau_diubah(): void
    {
        $user = User::factory()->create();
        $tema = Tema::factory()->create();
        $milikSendiri = Undangan::factory()->create(['user_id' => $user->id]);
        $orangLain = Undangan::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->komponen($user)->call('pilihUndangan', $orangLain->id)->assertNotFound();

        // Param query milik orang lain diabaikan: jatuh ke undangan sendiri.
        $this->komponen($user, ['undangan' => $orangLain->id])->assertSet('undanganId', $milikSendiri->id);

        $this->assertNull($orangLain->fresh()->tema_id);
    }

    public function test_properti_id_dikunci_dari_manipulasi_klien(): void
    {
        $user = User::factory()->create();
        $orangLain = Undangan::factory()->create(['user_id' => User::factory()->create()->id]);
        Undangan::factory()->create(['user_id' => $user->id]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->set('undanganId', $orangLain->id);
    }

    public function test_tema_draf_atau_terhapus_ditolak_404(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->create(['user_id' => $user->id]);
        $draf = Tema::factory()->draft()->create();
        $hapus = Tema::factory()->create();
        $hapus->delete();

        $this->komponen($user)->call('pakai', $draf->id)->assertNotFound();
        $this->komponen($user)->call('pakai', $hapus->id)->assertNotFound();
        $this->komponen($user)->call('pratinjau', $draf->id)->assertNotFound();
        $this->komponen($user)->call('pakai', 999999)->assertNotFound();

        $this->assertNull($u->fresh()->tema_id);
    }

    public function test_tema_terkunci_menurut_paket_ditolak(): void
    {
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota' => ['gratis' => 5]]);
        $user = User::factory()->create();
        $u = Undangan::factory()->create(['user_id' => $user->id]);
        $premium = Tema::factory()->tier('Premium')->create(['nama' => 'Premium Satu']);
        $gratis = Tema::factory()->create(['nama' => 'Gratis Satu']);

        $k = $this->komponen($user)->call('pakai', $premium->id)->assertSee('perlu paket Premium');
        $this->assertNull($u->fresh()->tema_id);

        $k->call('pakai', $gratis->id);
        $this->assertSame($gratis->id, $u->fresh()->tema_id);
    }

    public function test_tema_premium_bebas_saat_paket_belum_diaktifkan(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->create(['user_id' => $user->id]);
        $premium = Tema::factory()->tier('Deluxe')->create();

        $this->komponen($user)->call('pakai', $premium->id);

        $this->assertSame($premium->id, $u->fresh()->tema_id);
    }

    public function test_tanpa_undangan_tema_tidak_bisa_diterapkan(): void
    {
        $tema = Tema::factory()->create();

        $this->komponen(User::factory()->create())
            ->assertSee('Belum ada undangan tersimpan')
            ->assertDontSee('Pakai tema')
            ->call('pakai', $tema->id)
            ->assertSee('Buat undangan dulu');
    }

    public function test_tema_yang_sudah_dipakai_tidak_mengubah_apa_pun(): void
    {
        $user = User::factory()->create();
        $tema = Tema::factory()->create();
        $u = Undangan::factory()->terbit()->create(['user_id' => $user->id, 'tema_id' => $tema->id]);

        $this->komponen($user)->call('pakai', $tema->id)->assertSee('sudah dipakai undangan ini');

        $this->assertFalse($u->fresh()->ada_perubahan);
    }

    public function test_pratinjau_membuka_dan_menutup_dialog(): void
    {
        $tema = Tema::factory()->create(['nama' => 'Zamrud']);

        $this->komponen(User::factory()->create())
            ->call('pratinjau', $tema->id)
            ->assertSet('tampilPratinjau', true)
            ->assertSet('pratinjauId', $tema->id)
            ->assertSee('Pratinjau tema Zamrud')
            ->assertSee('Palet warna')
            ->call('tutupPratinjau')
            ->assertSet('tampilPratinjau', false);
    }

    public function test_pratinjau_penuh_tidak_error_dengan_atau_tanpa_renderer(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->create(['user_id' => $user->id]);
        $tema = Tema::factory()->create();

        $this->komponen($user)->call('pratinjau', $tema->id)->set('modePratinjau', 'penuh')->assertOk();
    }

    public function test_pakai_dibatasi_rate_limiter(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->create(['user_id' => $user->id]);
        $tema = Tema::factory()->create();
        RateLimiter::clear('template-pakai:'.$user->id);
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit('template-pakai:'.$user->id, 60);
        }

        $this->komponen($user)->call('pakai', $tema->id)->assertSee('Terlalu banyak percobaan');

        $this->assertNull($u->fresh()->tema_id);
    }

    public function test_tema_tidak_tersedia_lagi_ditampilkan_sebagai_tema_aktif(): void
    {
        $user = User::factory()->create();
        $hilang = Tema::factory()->draft()->create();
        Undangan::factory()->create(['user_id' => $user->id, 'tema_id' => $hilang->id]);

        $this->komponen($user)->assertSee('Tema tidak tersedia lagi');
    }
}
