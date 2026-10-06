<?php

namespace Tests\Feature;

use App\Livewire\BuatUndangan;
use App\Models\Pengaturan;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class BuatUndanganTest extends TestCase
{
    use RefreshDatabase;

    private function isiLangkahDua($komponen, array $ubah = [])
    {
        $data = array_merge([
            'pria' => 'Raka', 'wanita' => 'Sinta', 'tanggal' => now()->addMonths(2)->toDateString(),
            'waktu' => '09:00', 'lokasi' => 'Grand Ballroom, Jakarta',
        ], $ubah);
        foreach ($data as $k => $v) {
            $komponen->set($k, $v);
        }

        return $komponen;
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/buat-undangan')->assertRedirect(route('masuk'));
    }

    public function test_akun_belum_terverifikasi_ditolak(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/buat-undangan')->assertRedirect();
    }

    public function test_rute_menampilkan_halaman_asli_bukan_placeholder(): void
    {
        $this->assertSame('/buat-undangan', route('buat-undangan', absolute: false));

        $this->actingAs(User::factory()->create())->get('/buat-undangan')
            ->assertOk()
            ->assertSeeLivewire(BuatUndangan::class)
            ->assertSee('Acara apa yang akan kamu rayakan?');
    }

    public function test_alur_utama_membuat_undangan_draf_dan_ke_beranda_bila_editor_belum_ada(): void
    {
        $user = User::factory()->create();
        $tema = Tema::factory()->create(['nama' => 'Biru']);

        $k = Livewire::actingAs($user)->test(BuatUndangan::class)->call('lanjut')->assertSet('langkah', 2);
        $this->isiLangkahDua($k)
            ->assertSet('judul', 'Raka & Sinta')
            ->assertSet('slug', 'raka-sinta')
            ->call('lanjut')->assertHasNoErrors()->assertSet('langkah', 3)
            ->call('buat')
            ->assertRedirect(route('beranda'));

        $u = Undangan::firstOrFail();
        $this->assertSame($user->id, $u->user_id);
        $this->assertSame($tema->id, $u->tema_id);
        $this->assertSame(Undangan::DRAF, $u->status);
        $this->assertSame('raka-sinta', $u->slug);
        $this->assertSame('Raka & Sinta', $u->judul);
        $this->assertSame('Pernikahan', $u->jenis);
        $this->assertSame(now()->addMonths(2)->toDateString(), $u->tanggal_acara->toDateString());
        $this->assertSame('Grand Ballroom, Jakarta', $u->lokasi_ringkas);
        $this->assertSame('Raka', $u->isi['priaP']);
        $this->assertSame('09:00', $u->isi['hmJam']);
        $this->assertSame('Raka & Sinta', $u->isi['ptNama']);
        $this->assertSame([1, 2], $u->isi['_daftar']['ev']);
        $this->assertSame('Grand Ballroom, Jakarta', $u->isi['ev1_lokasi']);
        $this->assertTrue($u->langkah['tema']);
        $this->assertFalse($u->langkah['terbit']);
        $this->assertContains('sampul', $u->bagian['urutan']);
    }

    public function test_diarahkan_ke_editor_bila_rutenya_ada(): void
    {
        Route::middleware('web')->get('/uji-editor/{undangan}', fn () => 'ok')->name('editor');
        app('router')->getRoutes()->refreshNameLookups();

        $user = User::factory()->create();
        Tema::factory()->create();
        $k = Livewire::actingAs($user)->test(BuatUndangan::class)->call('lanjut');
        $this->isiLangkahDua($k)->call('lanjut')->call('buat');

        $k->assertRedirect('/uji-editor/'.Undangan::firstOrFail()->id);
    }

    public function test_validasi_langkah_dua_gagal(): void
    {
        $user = User::factory()->create();
        Tema::factory()->create();

        Livewire::actingAs($user)->test(BuatUndangan::class)->call('lanjut')
            ->set('judul', '')->set('tanggal', '')->set('slug', '')
            ->call('lanjut')
            ->assertHasErrors(['judul' => 'required', 'tanggal' => 'required', 'slug' => 'required'])
            ->assertSet('langkah', 2);

        $this->assertSame(0, Undangan::count());
    }

    public function test_galat_hilang_setelah_isian_diperbaiki(): void
    {
        Tema::factory()->create();
        Livewire::actingAs(User::factory()->create())->test(BuatUndangan::class)->call('lanjut')
            ->call('lanjut')->assertHasErrors('tanggal')
            ->set('tanggal', now()->addDay()->toDateString())->assertHasNoErrors('tanggal');
    }

    public function test_tanggal_lampau_dan_slug_terlarang_ditolak(): void
    {
        $user = User::factory()->create();
        Tema::factory()->create();

        $k = Livewire::actingAs($user)->test(BuatUndangan::class)->call('lanjut');
        $this->isiLangkahDua($k, ['tanggal' => now()->subDay()->toDateString(), 'pria' => '', 'wanita' => ''])
            ->set('judul', 'Pesta')
            ->set('slug', 'admin')
            ->call('lanjut')
            ->assertHasErrors(['tanggal', 'slug'])
            ->assertSet('langkah', 2);
    }

    public function test_slug_bentrok_ditolak_dan_slug_otomatis_dibuat_unik(): void
    {
        Undangan::factory()->create(['slug' => 'raka-sinta']);
        $user = User::factory()->create();
        Tema::factory()->create();

        $k = Livewire::actingAs($user)->test(BuatUndangan::class)->call('lanjut');
        $this->isiLangkahDua($k)->assertSet('slug', 'raka-sinta-2');

        $k->set('slug', 'raka-sinta')->assertHasErrors('slug')->call('lanjut')->assertSet('langkah', 2);
        $this->assertSame(1, Undangan::count());
    }

    public function test_slug_diambil_pengguna_lain_saat_buat_kembali_ke_langkah_dua(): void
    {
        $user = User::factory()->create();
        Tema::factory()->create();

        $k = Livewire::actingAs($user)->test(BuatUndangan::class)->call('lanjut');
        $this->isiLangkahDua($k)->call('lanjut')->assertSet('langkah', 3);

        Undangan::factory()->create(['slug' => 'raka-sinta']);
        $k->call('buat')->assertSet('langkah', 2)->assertHasErrors('slug');
        $this->assertSame(1, Undangan::count());
    }

    public function test_langkah_dan_tema_tidak_bisa_diubah_dari_klien(): void
    {
        $k = Livewire::actingAs(User::factory()->create())->test(BuatUndangan::class);
        foreach (['langkah' => 3, 'temaId' => 999] as $prop => $nilai) {
            try {
                $k->set($prop, $nilai);
                $this->fail("Properti {$prop} seharusnya terkunci.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_buat_dari_langkah_satu_tidak_membuat_apa_apa(): void
    {
        Tema::factory()->create();
        Livewire::actingAs(User::factory()->create())->test(BuatUndangan::class)->call('buat')->assertNoRedirect();
        $this->assertSame(0, Undangan::count());
    }

    public function test_tema_nonaktif_terhapus_atau_tidak_ada_tidak_bisa_dipilih(): void
    {
        $user = User::factory()->create();
        $aktif = Tema::factory()->create();
        $draf = Tema::factory()->draft()->create();
        $hapus = Tema::factory()->create();
        $hapus->delete();

        $k = Livewire::actingAs($user)->test(BuatUndangan::class)->assertSet('temaId', $aktif->id);
        foreach ([$draf->id, $hapus->id, 99999] as $id) {
            $k->call('pilihTema', $id)->assertSet('temaId', $aktif->id)->assertSee('Pilih tema yang tersedia');
        }

        $lain = Tema::factory()->create();
        $k->call('pilihTema', $lain->id)->assertSet('temaId', $lain->id);

        $this->isiLangkahDua($k->call('lanjut'))->call('lanjut')->call('buat');
        $this->assertSame($lain->id, Undangan::firstOrFail()->tema_id);
    }

    public function test_tanpa_tema_terbit_tidak_bisa_membuat(): void
    {
        Tema::factory()->draft()->create();
        $k = Livewire::actingAs(User::factory()->create())->test(BuatUndangan::class)->assertSet('temaId', null)->call('lanjut');
        $this->isiLangkahDua($k)->call('lanjut')->call('buat')->assertNoRedirect()->assertSee('Pilih tema yang tersedia');
        $this->assertSame(0, Undangan::count());
    }

    public function test_tema_berbayar_terkunci_bila_paket_aktif(): void
    {
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota' => ['gratis' => 5]]);
        $gratis = Tema::factory()->create();
        $premium = Tema::factory()->tier('Premium')->create();

        Livewire::actingAs(User::factory()->create())->test(BuatUndangan::class)
            ->call('pilihTema', $premium->id)->assertSet('temaId', $gratis->id);
    }

    public function test_tema_berbayar_boleh_bila_paket_tidak_dikonfigurasi(): void
    {
        Tema::factory()->create();
        $premium = Tema::factory()->tier('Premium')->create();

        Livewire::actingAs(User::factory()->create())->test(BuatUndangan::class)
            ->call('pilihTema', $premium->id)->assertSet('temaId', $premium->id);
    }

    public function test_kuota_penuh_menahan_langkah_dan_pembuatan(): void
    {
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota' => ['gratis' => 1]]);
        $user = User::factory()->create();
        Tema::factory()->create();
        Undangan::factory()->create(['user_id' => $user->id]);

        $k = Livewire::actingAs($user)->test(BuatUndangan::class)
            ->assertSee('Kuota undangan habis')
            ->call('lanjut')->assertSet('langkah', 1);
        $this->assertSame(1, Undangan::count());
    }

    public function test_kuota_dihitung_per_pengguna(): void
    {
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota' => ['gratis' => 1]]);
        Undangan::factory()->create();
        Tema::factory()->create();

        Livewire::actingAs(User::factory()->create())->test(BuatUndangan::class)
            ->assertDontSee('Kuota undangan habis')->call('lanjut')->assertSet('langkah', 2);
    }

    public function test_tanpa_batas_bila_pengaturan_paket_tidak_ada(): void
    {
        $user = User::factory()->create();
        Tema::factory()->create();
        Undangan::factory()->count(3)->create(['user_id' => $user->id]);

        Livewire::actingAs($user)->test(BuatUndangan::class)->assertDontSee('Kuota undangan habis')
            ->call('lanjut')->assertSet('langkah', 2);
    }

    public function test_pembatasan_laju_pembuatan(): void
    {
        $user = User::factory()->create();
        Tema::factory()->create();
        $k = Livewire::actingAs($user)->test(BuatUndangan::class)->call('lanjut');
        $this->isiLangkahDua($k)->call('lanjut');

        RateLimiter::clear('buat-undangan:'.$user->id);
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('buat-undangan:'.$user->id, 60);
        }
        $k->call('buat')->assertNoRedirect()->assertSee('Terlalu banyak percobaan');
        $this->assertSame(0, Undangan::count());
    }

    public function test_undangan_milik_pengguna_yang_login(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Tema::factory()->create();

        $k = Livewire::actingAs($a)->test(BuatUndangan::class)->call('lanjut');
        $this->isiLangkahDua($k)->call('lanjut')->call('buat');

        $this->assertSame(1, Undangan::where('user_id', $a->id)->count());
        $this->assertSame(0, Undangan::where('user_id', $b->id)->count());
    }
}
