<?php

namespace Tests\Feature;

use App\Livewire\Pratinjau;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use App\Support\SkemaUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PratinjauTest extends TestCase
{
    use RefreshDatabase;

    private function bagian(): array
    {
        return ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];
    }

    private function draf(User $user, array $isi = ['priaP' => 'Draf Raka'], array $atribut = []): Undangan
    {
        return Undangan::factory()->for($user, 'pengguna')->dengan(Tema::factory()->create())
            ->create(array_merge(['isi' => $isi, 'bagian' => $this->bagian()], $atribut));
    }

    private function terbit(User $user, array $atribut = []): Undangan
    {
        $isi = array_replace(SkemaUndangan::isiAwal(), ['priaP' => 'Pria Terbit']);

        return $this->draf($user, array_replace($isi, ['priaP' => 'Pria Draf']), array_merge([
            'status' => Undangan::TERBIT, 'diterbitkan_at' => now(), 'ada_perubahan' => true,
            'terbitan' => ['isi' => $isi, 'bagian' => $this->bagian()],
        ], $atribut));
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/pratinjau')->assertRedirect(route('masuk'));
        $this->get('/pratinjau/bingkai/1')->assertRedirect(route('masuk'));
    }

    public function test_akun_belum_terverifikasi_ditolak(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/pratinjau')->assertRedirect();
    }

    public function test_rute_menampilkan_halaman_asli_bukan_placeholder(): void
    {
        $this->assertSame('/pratinjau', route('pratinjau', absolute: false));

        $this->actingAs(User::factory()->create())->get('/pratinjau')
            ->assertOk()
            ->assertSeeLivewire(Pratinjau::class)
            ->assertSee('Belum ada undangan');
    }

    public function test_tanpa_parameter_memakai_undangan_terbaru_dan_menyertakan_bingkai(): void
    {
        $user = User::factory()->create();
        $lama = $this->draf($user, ['priaP' => 'Lama']);
        $lama->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $baru = $this->draf($user, ['priaP' => 'Baru']);

        Livewire::actingAs($user)->test(Pratinjau::class)
            ->assertSet('undanganId', $baru->id)
            ->assertSee('Draf')
            ->assertSee('/pratinjau/bingkai/'.$baru->id, false)
            ->assertSee('Muat ulang pratinjau');
    }

    public function test_undangan_orang_lain_404_di_halaman_dan_bingkai(): void
    {
        $pemilik = User::factory()->create();
        $lain = User::factory()->create();
        $milikLain = $this->draf($lain);

        $this->actingAs($pemilik)->get('/pratinjau/'.$milikLain->id)->assertNotFound();
        $this->actingAs($pemilik)->get('/pratinjau/bingkai/'.$milikLain->id)->assertNotFound();
        $this->actingAs($pemilik)->get('/pratinjau/9999')->assertNotFound();
        $this->actingAs($lain)->get('/pratinjau/bingkai/'.$milikLain->id)->assertOk();
    }

    public function test_buka_di_tab_baru_versi_terbit_mengarah_ke_alamat_publik_draf_tetap_bingkai(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, ['slug' => 'pasangan-bahagia']);

        $this->actingAs($user)->get('/pratinjau/'.$u->id)
            ->assertSee('href="'.route('undangan.publik', 'pasangan-bahagia').'" target="_blank"', false);

        $draf = $this->draf($user);
        $this->actingAs($user)->get('/pratinjau/'.$draf->id)
            ->assertSee('/pratinjau/bingkai/'.$draf->id, false)->assertSee('data-tab-baru', false);
    }

    public function test_tanpa_parameter_tidak_pernah_memilih_undangan_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $this->draf(User::factory()->create(['name' => 'Orang Lain']));

        Livewire::actingAs($pemilik)->test(Pratinjau::class)->assertSet('undanganId', null)->assertSee('Belum ada undangan');
    }

    public function test_pilih_undangan_milik_orang_lain_ditolak(): void
    {
        $pemilik = User::factory()->create();
        $milikLain = $this->draf(User::factory()->create());
        $sendiri = $this->draf($pemilik);

        Livewire::actingAs($pemilik)->test(Pratinjau::class)
            ->call('pilihUndangan', $milikLain->id)->assertNotFound();

        Livewire::actingAs($pemilik)->test(Pratinjau::class)
            ->call('pilihUndangan', $sendiri->id)->assertRedirect(route('pratinjau', ['undangan' => $sendiri->id]));
    }

    public function test_id_undangan_terkunci_dari_manipulasi_klien(): void
    {
        $pemilik = User::factory()->create();
        $milikLain = $this->draf(User::factory()->create());
        $this->draf($pemilik);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($pemilik)->test(Pratinjau::class)->set('undanganId', $milikLain->id);
    }

    public function test_bingkai_draf_memuat_isi_draf_dengan_header_keamanan(): void
    {
        $user = User::factory()->create();
        $u = $this->draf($user, array_replace(SkemaUndangan::isiAwal(), ['priaP' => 'Pria Draf']));

        $res = $this->actingAs($user)->get('/pratinjau/bingkai/'.$u->id);

        $res->assertOk()->assertSee('Pria Draf')->assertSee('class="uy-undangan"', false);
        $this->assertStringContainsString("default-src 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('noindex', $res->getContent());
        $this->assertSame('SAMEORIGIN', $res->headers->get('X-Frame-Options'));
    }

    public function test_bingkai_undangan_kosong_404(): void
    {
        $user = User::factory()->create();
        $u = $this->draf($user, []);

        $this->actingAs($user)->get('/pratinjau/bingkai/'.$u->id)->assertNotFound();
        Livewire::actingAs($user)->test(Pratinjau::class)
            ->assertSee('Isi undangan belum tersimpan')->assertSee('Draf kosong')->assertDontSee('/pratinjau/bingkai/'.$u->id, false);
    }

    public function test_versi_terbit_dan_draf(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $this->actingAs($user)->get('/pratinjau/bingkai/'.$u->id)->assertSee('Pria Terbit')->assertDontSee('Pria Draf');
        $this->actingAs($user)->get('/pratinjau/bingkai/'.$u->id.'?v=draf')->assertSee('Pria Draf')->assertDontSee('Pria Terbit');

        Livewire::actingAs($user)->test(Pratinjau::class, ['undangan' => $u->id])
            ->assertSee('Draf terbaru')
            ->assertSee('Tampilan yang dilihat tamu.')
            ->assertSee('Salin link')
            ->call('pilihVersi', 'draf')
            ->assertSet('versi', 'draf')
            ->assertSee('Menampilkan draf terakhir dari editor.')
            ->assertSee('v=draf', false);
    }

    public function test_pilihan_versi_diabaikan_bila_tidak_ada_draf_berbeda(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user, ['ada_perubahan' => false]);
        $u->update(['isi' => $u->terbitan['isi']]);

        $this->actingAs($user)->get('/pratinjau/bingkai/'.$u->id.'?v=draf')->assertSee('Pria Terbit');
        Livewire::actingAs($user)->test(Pratinjau::class, ['undangan' => $u->id])->assertDontSee('Draf terbaru');
    }

    public function test_draf_tidak_menawarkan_salin_link(): void
    {
        $user = User::factory()->create();
        $u = $this->draf($user, SkemaUndangan::isiAwal(), ['slug' => 'raka-sinta-xyz']);

        Livewire::actingAs($user)->test(Pratinjau::class, ['undangan' => $u->id])
            ->assertSee('(belum terbit)')
            ->assertSee('Dari draf editor, belum diterbitkan.')
            ->assertDontSee('x-text="ok', false);
    }

    public function test_perangkat_dipilih_dan_nilai_tak_dikenal_jadi_ponsel(): void
    {
        $user = User::factory()->create();
        $this->draf($user);

        Livewire::actingAs($user)->test(Pratinjau::class)
            ->assertSet('perangkat', 'ponsel')
            ->call('pilihPerangkat', 'tablet')->assertSet('perangkat', 'tablet')->assertSee('width:720px', false)
            ->call('pilihPerangkat', 'x')->assertSet('perangkat', 'ponsel')->assertSee('width:412px', false);
    }

    public function test_muat_ulang_menaikkan_rev_pada_alamat_bingkai(): void
    {
        $user = User::factory()->create();
        $u = $this->draf($user);

        Livewire::actingAs($user)->test(Pratinjau::class)
            ->assertSet('rev', 1)->call('muatUlang')->assertSet('rev', 2)->assertSee('r=2', false);
    }

    public function test_mode_contoh_untuk_pengguna_tanpa_undangan(): void
    {
        $user = User::factory()->create();
        Tema::factory()->create();

        Livewire::actingAs($user)->test(Pratinjau::class)
            ->call('tampilkanContoh')->assertSee('Mode contoh')->assertSee('/pratinjau/bingkai/contoh', false)
            ->call('sembunyikanContoh')->assertDontSee('Mode contoh');

        $this->actingAs($user)->get('/pratinjau/bingkai/contoh')->assertOk()->assertSee('THE WEDDING OF');
    }

    public function test_edit_dan_bagikan_mengarah_ke_undangan_ini(): void
    {
        $user = User::factory()->create();
        $u = $this->draf($user);

        Livewire::actingAs($user)->test(Pratinjau::class, ['undangan' => $u->id])
            ->assertSee('Edit undangan')->assertSee('Bagikan')->assertSee('undangan='.$u->id, false);
    }

    public function test_judul_undangan_di_pemilih_di_escape(): void
    {
        $user = User::factory()->create();
        $this->draf($user, ['priaP' => 'x'], ['judul' => '<script>alert(1)</script>']);

        Livewire::actingAs($user)->test(Pratinjau::class)->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
