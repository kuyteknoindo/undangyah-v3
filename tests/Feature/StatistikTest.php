<?php

namespace Tests\Feature;

use App\Livewire\Statistik;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\StatistikUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class StatistikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Selasa 6 Okt 2026, tengah hari (zona aplikasi).
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(Statistik::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    private function undangan(User $user, array $lain = []): Undangan
    {
        return Undangan::factory()->terbit()->create(array_merge(['user_id' => $user->id, 'slug' => 'raka-sinta', 'judul' => 'Raka & Sinta'], $lain));
    }

    private function lihat(Undangan $u, string $waktu, ?Tamu $tamu = null): Tayangan
    {
        return Tayangan::create(['undangan_id' => $u->id, 'tamu_id' => $tamu?->id, 'kode_tamu' => $tamu?->kode, 'created_at' => Carbon::parse($waktu, 'Asia/Jakarta')]);
    }

    private function tamu(Undangan $u, string $nama, bool $terkirim = false): Tamu
    {
        return Tamu::factory()->create(['undangan_id' => $u->id, 'nama' => $nama, 'kode' => strtolower(substr(md5($nama.$u->id), 0, 10)), 'dikirim_at' => $terkirim ? now()->subDays(2) : null]);
    }

    private function rsvp(Undangan $u, Tamu $t, string $status): Rsvp
    {
        return Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => $t->id, 'nama' => $t->nama, 'status' => $status, 'jumlah' => $status === 'hadir' ? 1 : 0]);
    }

    // ------------------------------------------------------------------ akses

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/statistik')->assertRedirect(route('masuk'));
    }

    public function test_rute_asli_bukan_placeholder(): void
    {
        $this->assertSame('/statistik', route('statistik', absolute: false));
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->actingAs($user)->get('/statistik')->assertOk()->assertSeeLivewire(Statistik::class);
        $this->actingAs($user)->get('/statistik/'.$u->id)->assertOk();
    }

    public function test_keadaan_kosong_tanpa_undangan(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada undangan');
    }

    public function test_undangan_orang_lain_404_dan_angkanya_tidak_bocor(): void
    {
        $user = User::factory()->create();
        $punya = $this->undangan($user);
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);
        foreach (range(1, 5) as $i) {
            $this->lihat($lain, '2026-10-06 09:00:00');
        }
        $this->tamu($lain, 'Tamu Rahasia');

        $this->actingAs($user)->get('/statistik/'.$lain->id)->assertNotFound();
        $this->komponen($user)->call('pilihUndangan', $lain->id)->assertStatus(404);
        $this->komponen($user)->assertDontSee('Tamu Rahasia')->assertSet('undanganId', $punya->id);
        $this->assertSame(0, app(StatistikUndangan::class)->hitung($punya, 7)['kunjungan']['total']);

        $c = $this->komponen($user);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c->set('undanganId', $lain->id);
    }

    // ------------------------------------------------------------------ angka

    public function test_rentang_waktu_menghitung_kunjungan_dan_pembanding(): void
    {
        $u = $this->undangan(User::factory()->create());
        $this->lihat($u, '2026-10-06 08:00:00');   // hari ini
        $this->lihat($u, '2026-10-06 23:59:59');   // masih hari ini
        $this->lihat($u, '2026-09-30 10:00:00');   // 6 hari lalu: awal rentang 7 hari
        $this->lihat($u, '2026-09-29 23:59:59');   // di luar 7 hari, periode pembanding
        $this->lihat($u, '2026-09-20 10:00:00');   // dalam 30 hari
        $this->lihat($u, '2026-08-10 10:00:00');   // dalam 90 hari
        $this->lihat($u, '2026-06-01 10:00:00');   // di luar 90 hari
        $this->lihat($u, '2026-10-07 00:00:00');   // besok: tidak dihitung

        $s = app(StatistikUndangan::class);
        $tujuh = $s->hitung($u, 7)['kunjungan'];
        $this->assertSame(3, $tujuh['total']);
        $this->assertSame(1, $tujuh['sebelumnya']);
        $this->assertSame(200, $tujuh['delta']);
        $this->assertSame(5, $s->hitung($u, 30)['kunjungan']['total']);
        $this->assertSame(6, $s->hitung($u, 90)['kunjungan']['total']);
        $this->assertSame(3, $s->hitung($u, 12345)['kunjungan']['total'], 'rentang tak dikenal jatuh ke 7 hari');
    }

    public function test_pembanding_kosong_tidak_memberi_persen(): void
    {
        $u = $this->undangan(User::factory()->create());
        $this->lihat($u, '2026-10-06 08:00:00');

        $this->assertNull(app(StatistikUndangan::class)->hitung($u, 7)['kunjungan']['delta']);
    }

    public function test_bucket_harian_mingguan_jam_dan_hari(): void
    {
        $u = $this->undangan(User::factory()->create());
        $this->lihat($u, '2026-10-06 09:15:00'); // Selasa
        $this->lihat($u, '2026-10-06 09:45:00');
        $this->lihat($u, '2026-10-04 21:00:00'); // Minggu

        $s = app(StatistikUndangan::class);
        $d7 = $s->hitung($u, 7);
        $this->assertCount(7, $d7['kunjungan']['buckets']);
        $this->assertSame(2, $d7['kunjungan']['buckets'][6]['nilai']);
        $this->assertSame('Sel', $d7['kunjungan']['buckets'][6]['label']);
        $this->assertSame(1, $d7['kunjungan']['buckets'][4]['nilai']);
        $this->assertSame(2, $d7['jam'][9]);
        $this->assertSame(1, $d7['jam'][21]);
        $this->assertSame(3, array_sum($d7['jam']));
        $this->assertSame(2, $d7['hari'][1], 'Selasa');
        $this->assertSame(1, $d7['hari'][6], 'Minggu');

        $this->assertCount(30, $s->hitung($u, 30)['kunjungan']['buckets']);
        $d90 = $s->hitung($u, 90)['kunjungan']['buckets'];
        $this->assertCount(13, $d90);
        $this->assertSame(3, array_sum(array_column($d90, 'nilai')));
    }

    public function test_alur_tamu_dari_tamu_yang_terkirim(): void
    {
        $u = $this->undangan(User::factory()->create());
        $a = $this->tamu($u, 'Ani', true);   // terkirim, dilihat, hadir
        $b = $this->tamu($u, 'Budi', true);  // terkirim, dilihat, tidak hadir
        $c = $this->tamu($u, 'Citra', true); // terkirim, dilihat saja
        $d = $this->tamu($u, 'Dewi', true);  // terkirim, belum dilihat
        $e = $this->tamu($u, 'Eko', false);  // belum dikirim, membuka sendiri
        $this->tamu($u, 'Fajar', false);     // belum apa-apa
        $this->lihat($u, '2026-10-05 10:00:00', $a);
        $this->lihat($u, '2026-10-05 10:00:00', $c);
        $this->lihat($u, '2026-10-05 10:00:00', $e);
        $this->rsvp($u, $a, 'hadir');
        $this->rsvp($u, $b, 'tidak');
        Ucapan::create(['undangan_id' => $u->id, 'nama' => 'X', 'pesan' => 'Selamat']);
        Ucapan::create(['undangan_id' => $u->id, 'nama' => 'Y', 'pesan' => 'Selamat', 'disembunyikan' => true]);

        $h = app(StatistikUndangan::class)->hitung($u, 7);

        $this->assertSame(6, $h['tamu']['total']);
        $this->assertSame(4, $h['tamu']['terkirim']);
        $this->assertSame(4, $h['tamu']['dilihat'], 'Ani, Budi (lewat RSVP), Citra, Eko');
        $this->assertSame([4, 3, 2, 1], $h['tamu']['alur']);
        $this->assertSame(['total' => 2, 'hadir' => 1, 'tidak' => 1], $h['rsvp']);
        $this->assertSame(2, $h['ucapan']);

        $belum = app(StatistikUndangan::class)->belumMembuka($u, 10);
        $this->assertSame(2, $belum['jumlah']);
        $this->assertSame(['Dewi', 'Fajar'], $belum['daftar']->pluck('nama')->all(), 'yang sudah dikirim dulu');
    }

    public function test_tampilan_menampilkan_angka_dan_catatan_jujur_soal_sumber(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $this->lihat($u, '2026-10-06 09:00:00');
        $this->tamu($u, 'Dewi Lestari', true);

        $this->komponen($user)
            ->assertSee('Kunjungan undangan')->assertSee('Alur tamu')->assertSee('Tamu belum membuka')->assertSee('Dewi Lestari')
            ->assertSee('Kirim ulang')->assertSee('Kunjungan per jam')->assertSee('Kunjungan per hari')
            ->assertSee('Segera hadir')->assertSee('belum dicatat')
            ->assertSee('Puncak: Selasa, 6 Okt 2026: 1 kunjungan.');
    }

    public function test_pilih_rentang_dan_tampilan_tabel(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $this->lihat($u, '2026-09-20 10:00:00');

        $this->komponen($user)
            ->call('pilihRentang', 30)->assertSet('rentang', 30)->assertSee('30 hari terakhir')
            ->call('pilihRentang', 5)->assertSet('rentang', 7)
            ->call('pilihTampilan', 'tabel')->assertSet('tampilan', 'tabel')->assertSeeHtml('<table class="tabel">')
            ->call('pilihTampilan', 'aneh')->assertSet('tampilan', 'grafik');
    }

    // ------------------------------------------------------------------ kirim ke tamu yang belum membuka

    public function test_kirim_menandai_terkirim_dan_hanya_untuk_tamu_sendiri(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $t = $this->tamu($u, 'Dewi');
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);
        $asing = $this->tamu($lain, 'Asing');

        $c = $this->komponen($user)->assertSee('https://wa.me/62', false)->assertSee('Kirim undangan');
        $c->call('catatKirim', $t->id)->assertDispatched('toast', pesan: 'Dewi ditandai terkirim.', tipe: 'sukses');
        $this->assertNotNull($t->fresh()->dikirim_at);

        $c->call('catatKirim', $asing->id)->assertStatus(404);
        $this->assertNull($asing->fresh()->dikirim_at);
    }

    public function test_draf_tidak_bisa_ditandai_terkirim(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user, ['status' => Undangan::DRAF, 'terbitan' => null]);
        $t = $this->tamu($u, 'Dewi');

        $this->komponen($user)->assertSee('Terbitkan dulu')->call('catatKirim', $t->id)->assertDispatched('toast', pesan: 'Terbitkan undangan dulu sebelum mengirim.', tipe: 'galat');
        $this->assertNull($t->fresh()->dikirim_at);
    }

    public function test_daftar_belum_membuka_diringkas_lalu_bisa_dibuka(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        foreach (range(1, 9) as $i) {
            $this->tamu($u, 'Tamu '.$i);
        }

        $this->komponen($user)->assertSee('Tamu 1')->assertDontSee('Tamu 9')->assertSee('Lihat semua (9)')
            ->call('ubahSemuaBelum')->assertSee('Tamu 9')->assertSee('Tampilkan lebih sedikit');
    }
}
