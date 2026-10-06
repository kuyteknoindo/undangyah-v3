<?php

namespace Tests\Feature;

use App\Livewire\HubungiLayanan;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use App\Models\TiketBantuan;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class HubungiLayananTest extends TestCase
{
    use RefreshDatabase;

    private function isi($c, array $o = [])
    {
        $o += ['kategori' => 'akun', 'subjek' => 'Tidak bisa masuk', 'pesan' => 'Saya tidak bisa masuk sejak kemarin sore.'];

        return $c->call('pilihKategori', $o['kategori'])->set('subjek', $o['subjek'])->set('pesan', $o['pesan'])
            ->set('undanganId', (string) ($o['undangan'] ?? ''));
    }

    public function test_tamu_diarahkan_dan_pengguna_melihat_formulir(): void
    {
        $this->get('/hubungi')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/hubungi')
            ->assertOk()->assertSee('Kirim Pesan')->assertSee('Undangan dan desain')->assertSee('Belum ada tiket')
            ->assertDontSee('Chat WhatsApp');
    }

    public function test_kirim_membuat_tiket_milik_pengguna_dan_memberi_tahu_semua_admin(): void
    {
        $user = User::factory()->create(['name' => 'Budi', 'email' => 'budi@contoh.test']);
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();
        $undangan = Undangan::factory()->for($user, 'pengguna')->create();

        $c = Livewire::actingAs($user)->test(HubungiLayanan::class);
        $this->isi($c, ['undangan' => $undangan->id])->call('kirim')
            ->assertHasNoErrors()->assertSee('Pesan terkirim')->assertSee('TKT-'.now()->format('Y').'-0001')
            ->assertSet('subjek', '')->assertSet('kategori', '');

        $tiket = TiketBantuan::first();
        $this->assertSame($user->id, $tiket->user_id);
        $this->assertSame($undangan->id, $tiket->undangan_id);
        $this->assertSame('akun', $tiket->kategori);
        $this->assertSame('diproses', $tiket->status);
        $this->assertSame('TKT-'.now()->format('Y').'-0001', $tiket->nomor);

        foreach ([$admin1, $admin2] as $a) {
            $n = Notifikasi::where('user_id', $a->id)->first();
            $this->assertNotNull($n);
            $this->assertSame('Tiket bantuan baru', $n->judul);
            $this->assertStringContainsString('Budi (budi@contoh.test)', $n->isi);
            $this->assertStringContainsString('Tidak bisa masuk', $n->isi);
        }
        $this->assertSame(0, Notifikasi::where('user_id', $user->id)->count());
    }

    public function test_validasi_kategori_subjek_pesan(): void
    {
        $c = Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)
            ->call('kirim')
            ->assertHasErrors(['kategori' => 'required', 'subjek' => 'required', 'pesan' => 'required'])
            ->assertSee('Pilih kategori dulu.')->assertSee('Subjek wajib diisi.');

        $this->isi($c, ['pesan' => 'pendek'])->call('kirim')->assertHasErrors(['pesan' => 'min'])->assertSee('Pesan minimal 10 karakter.');
        $this->isi($c, ['subjek' => str_repeat('x', 121)])->call('kirim')->assertHasErrors(['subjek' => 'max']);
        $this->isi($c, ['pesan' => str_repeat('y', 1001)])->call('kirim')->assertHasErrors(['pesan' => 'max']);
        $c->set('kategori', 'sembarang')->call('kirim')->assertHasErrors(['kategori' => 'in']);

        $this->assertSame(0, TiketBantuan::count());
    }

    public function test_pilih_kategori_ngawur_dikosongkan(): void
    {
        Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)
            ->call('pilihKategori', 'lain')->assertSet('kategori', 'lain')
            ->call('pilihKategori', 'xx')->assertSet('kategori', '');
    }

    public function test_undangan_milik_orang_lain_atau_tak_ada_ditolak(): void
    {
        $user = User::factory()->create();
        $asing = Undangan::factory()->for(User::factory(), 'pengguna')->create();

        $c = Livewire::actingAs($user)->test(HubungiLayanan::class);
        $this->isi($c, ['undangan' => $asing->id])->call('kirim')->assertHasErrors(['undanganId' => 'exists']);
        $this->isi($c, ['undangan' => 999999])->call('kirim')->assertHasErrors(['undanganId']);
        $this->isi($c, ['undangan' => 'abc'])->call('kirim')->assertHasErrors(['undanganId']);

        $this->assertSame(0, TiketBantuan::count());
    }

    public function test_dibatasi_lima_tiket_per_jam(): void
    {
        $user = User::factory()->create();
        $c = Livewire::actingAs($user)->test(HubungiLayanan::class);

        for ($i = 1; $i <= 5; $i++) {
            $this->isi($c, ['subjek' => "Masalah $i"])->call('kirim')->assertHasNoErrors();
            $c->call('pesanLain');
        }
        $this->isi($c, ['subjek' => 'Masalah 6'])->call('kirim')->assertHasErrors('form')->assertSee('Kamu sudah mengirim 5 pesan');

        $this->assertSame(5, TiketBantuan::count());
        // Pengguna lain tidak terkena batas orang lain.
        $lain = Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class);
        $this->isi($lain)->call('kirim')->assertHasNoErrors();
        $this->assertSame(6, TiketBantuan::count());

        RateLimiter::clear('tiket-bantuan:'.$user->id);
    }

    public function test_kirim_ganda_isi_sama_tidak_membuat_tiket_kedua(): void
    {
        User::factory()->admin()->create();
        $c = Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class);
        $this->isi($c)->call('kirim');
        $this->isi($c)->call('kirim')->assertSee('Pesan terkirim');

        $this->assertSame(1, TiketBantuan::count());
        $this->assertSame(1, Notifikasi::count());
    }

    public function test_riwayat_hanya_tiket_sendiri_dengan_status(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();
        TiketBantuan::create(['nomor' => 'TKT-2026-0001', 'user_id' => $user->id, 'kategori' => 'bayar', 'subjek' => 'Tagihan saya', 'pesan' => 'Pesan rahasia saya', 'status' => 'selesai']);
        TiketBantuan::create(['nomor' => 'TKT-2026-0002', 'user_id' => $lain->id, 'kategori' => 'bayar', 'subjek' => 'Tagihan orang lain', 'pesan' => 'Pesan orang lain', 'status' => 'diproses']);

        $c = Livewire::actingAs($user)->test(HubungiLayanan::class)
            ->assertSee('Tagihan saya')->assertSee('Selesai')->assertSee('1 tiket')
            ->assertDontSee('Tagihan orang lain')->assertDontSee('TKT-2026-0002')
            ->assertDontSee('Pesan rahasia saya')
            ->call('ubahTiket', 1)->assertSee('Pesan rahasia saya');

        // Membuka id tiket orang lain tidak membocorkan isinya.
        $c->call('ubahTiket', TiketBantuan::where('user_id', $lain->id)->value('id'))->assertDontSee('Pesan orang lain');
    }

    public function test_riwayat_lihat_semua(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            TiketBantuan::create(['nomor' => "TKT-2026-000$i", 'user_id' => $user->id, 'kategori' => 'lain', 'subjek' => "Subjek nomor $i", 'pesan' => 'x']);
        }

        Livewire::actingAs($user)->test(HubungiLayanan::class)
            ->assertSee('Subjek nomor 6')->assertDontSee('Subjek nomor 1')->assertSee('Lihat semua (6)')
            ->call('ubahSemua')->assertSee('Subjek nomor 1')->assertSee('Tampilkan lebih sedikit');
    }

    public function test_id_tiket_baru_terkunci_dari_klien(): void
    {
        $lain = User::factory()->create();
        $t = TiketBantuan::create(['nomor' => 'TKT-2026-0009', 'user_id' => $lain->id, 'kategori' => 'lain', 'subjek' => 'Milik lain', 'pesan' => 'Pesan milik lain']);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)->set('tiketBaru', $t->id);
    }

    public function test_pesan_dirender_escape(): void
    {
        $user = User::factory()->create();
        TiketBantuan::create(['nomor' => 'TKT-2026-0001', 'user_id' => $user->id, 'kategori' => 'lain', 'subjek' => '<img src=x onerror=alert(1)>', 'pesan' => '<script>alert(2)</script>']);

        Livewire::actingAs($user)->test(HubungiLayanan::class)->call('ubahTiket', 1)
            ->assertDontSee('<script>alert(2)</script>', false)->assertDontSee('<img src=x', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_kontak_dari_pengaturan_tampil(): void
    {
        Pengaturan::simpan('kontak', ['whatsapp' => '6281234567890', 'email' => 'cs@contoh.test', 'jam_layanan' => '09.00 - 21.00 WIB', 'jam_buka' => '09:00', 'jam_tutup' => '21:00']);
        $this->travelTo(now()->setTime(10, 0));

        Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)
            ->assertSee('Chat WhatsApp')->assertSee('cs@contoh.test')->assertSee('Sedang buka sekarang');

        $this->travelTo(now()->setTime(22, 0));
        Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)->assertSee('Sedang tutup');
    }

    public function test_tiket_ikut_terhapus_bersama_pengguna(): void
    {
        $user = User::factory()->create();
        TiketBantuan::create(['nomor' => 'TKT-2026-0001', 'user_id' => $user->id, 'kategori' => 'lain', 'subjek' => 'x', 'pesan' => 'xxxxxxxxxxx']);
        $user->delete();

        $this->assertSame(0, TiketBantuan::count());
    }
}
