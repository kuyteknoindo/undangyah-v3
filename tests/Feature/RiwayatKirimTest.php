<?php

namespace Tests\Feature;

use App\Livewire\RiwayatKirim as KomponenRiwayat;
use App\Models\RiwayatKirim;
use App\Models\Tamu;
use App\Models\Undangan;
use App\Models\User;
use App\Services\CatatPengiriman;
use App\Services\RingkasanAkun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class RiwayatKirimTest extends TestCase
{
    use RefreshDatabase;

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(KomponenRiwayat::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    private function terbit(User $user, string $slug = 'raka-sinta'): Undangan
    {
        return Undangan::factory()->terbit()->create(['user_id' => $user->id, 'slug' => $slug, 'judul' => 'Raka & Sinta']);
    }

    private function tamu(Undangan $u, string $nama, array $atribut = []): Tamu
    {
        return Tamu::factory()->create(array_merge(['undangan_id' => $u->id, 'nama' => $nama, 'hp' => '628123456789'], $atribut));
    }

    private function log(Undangan $u, Tamu $t, array $atribut = []): RiwayatKirim
    {
        return RiwayatKirim::create(array_merge([
            'user_id' => $u->user_id, 'undangan_id' => $u->id, 'tamu_id' => $t->id, 'nama' => $t->nama, 'hp' => $t->hp,
            'jenis' => 'undangan', 'kanal' => 'whatsapp', 'status' => 'dibuka', 'created_at' => now(),
        ], $atribut));
    }

    public function test_tamu_diarahkan_ke_masuk_dan_halaman_asli(): void
    {
        $this->get('/riwayat-kirim')->assertRedirect(route('masuk'));
        $user = User::factory()->create();
        $this->terbit($user);
        $this->actingAs($user)->get('/riwayat-kirim')->assertOk()->assertSeeLivewire(KomponenRiwayat::class);
    }

    public function test_keadaan_kosong_tanpa_undangan_dan_tanpa_catatan(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada undangan');

        $user = User::factory()->create();
        $this->komponen($user, $this->terbit($user)->id)->assertSee('Belum ada pesan terkirim');
    }

    public function test_menampilkan_catatan_dan_tamu_belum_dikirimi(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $a = $this->tamu($u, 'Ani Sudah', ['dikirim_at' => now()]);
        $this->log($u, $a);
        $this->tamu($u, 'Budi Belum');

        $this->komponen($user, $u->id)->assertSee('Ani Sudah')->assertSee('Budi Belum')->assertSee('Dibuka di WhatsApp')->assertSee('Belum dikirim')
            ->assertSee('Menampilkan 1–2 dari 2 catatan');
    }

    public function test_tidak_memuat_data_undangan_atau_pengguna_lain(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $this->log($lain, $this->tamu($lain, 'Rahasia Orang Lain'));
        $this->tamu($lain, 'Belum Orang Lain');

        $this->komponen($user, $u->id)->assertDontSee('Rahasia Orang Lain')->assertDontSee('Belum Orang Lain');
        $this->actingAs($user)->get('/riwayat-kirim?undangan='.$lain->id)->assertNotFound();
        $this->komponen($user)->call('pilih', $lain->id)->assertNotFound();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->set('undanganId', $lain->id);
    }

    public function test_ringkasan_angka(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $a = $this->tamu($u, 'A', ['dikirim_at' => now()]);
        $this->log($u, $a);
        $this->log($u, $a);
        $this->tamu($u, 'B', ['hp' => null]);
        $this->tamu($u, 'C');

        $c = $this->komponen($user, $u->id);
        $html = $c->html();
        foreach ([['2', 'Pesan tercatat'], ['1', 'Tamu sudah dikirimi'], ['2', 'Tamu belum dikirimi'], ['1', 'Tamu tanpa nomor WhatsApp']] as [$angka, $label]) {
            $this->assertMatchesRegularExpression('/kpi-v[^>]*>'.$angka.'<\/div><div class="kpi-l">'.preg_quote($label, '/').'</', $html);
        }
    }

    public function test_cari_filter_status_kanal_dan_tanggal(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $a = $this->tamu($u, 'Ani Lama', ['dikirim_at' => now()->subDays(40)]);
        $b = $this->tamu($u, 'Budi Baru', ['dikirim_at' => now()]);
        $this->tamu($u, 'Citra Belum');
        $this->log($u, $a, ['created_at' => now()->subDays(40)]);
        $this->log($u, $b);

        $c = $this->komponen($user, $u->id);
        $c->set('q', 'ani')->assertSee('Ani Lama')->assertDontSee('Budi Baru')->assertDontSee('Citra Belum');
        $c->set('q', '')->set('tgl', '7')->assertSee('Budi Baru')->assertDontSee('Ani Lama')->assertDontSee('Citra Belum');
        $c->set('tgl', 'semua')->call('aturStatus', 'belum')->assertSee('Citra Belum')->assertDontSee('Ani Lama');
        $c->call('aturStatus', 'dibuka')->assertSee('Ani Lama')->assertSee('Budi Baru')->assertDontSee('Citra Belum');
        $c->call('aturStatus', 'semua')->set('kanal', 'email')->assertSee('Riwayat tidak ditemukan');
        $c->call('resetFilter')->assertSee('Ani Lama');
        $c->set('tgl', 'ngawur')->assertSet('tgl', 'semua');
        $c->call('aturStatus', 'ngawur')->assertSet('status', 'semua');
    }

    public function test_pencarian_menganggap_karakter_like_sebagai_teks_biasa(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, 'Ani');
        $this->tamu($u, '100% Budi');

        $this->komponen($user, $u->id)->set('q', '%')->assertSee('100% Budi')->assertDontSee('Ani');
    }

    public function test_paginasi_menggabung_catatan_lalu_tamu_belum_dikirimi(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        for ($i = 1; $i <= 12; $i++) {
            $t = $this->tamu($u, sprintf('Log %02d', $i), ['dikirim_at' => now()]);
            $this->log($u, $t, ['created_at' => now()->subMinutes(30 - $i)]);
        }
        for ($i = 1; $i <= 3; $i++) {
            $this->tamu($u, sprintf('Belum %02d', $i));
        }

        $c = $this->komponen($user, $u->id)->assertSee('Menampilkan 1–10 dari 15 catatan')->assertSee('Log 12')->assertDontSee('Log 02')->assertDontSee('Belum 01');
        $c->call('berikutnya')->assertSee('Menampilkan 11–15 dari 15 catatan')->assertSee('Log 02')->assertSee('Log 01')->assertSee('Belum 01')->assertSee('Belum 03')->assertDontSee('Log 12');
        $c->call('berikutnya')->assertSet('hal', 2);
        $c->call('sebelumnya')->call('sebelumnya')->assertSet('hal', 1);
    }

    public function test_kirim_dari_riwayat_mencatat_dan_menolak_tamu_orang_lain(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, 'Pak Budi');
        $asing = $this->tamu($this->terbit(User::factory()->create(), 'milik-lain'), 'Asing');

        $this->komponen($user, $u->id)->call('kirim', $t->id);
        $this->assertNotNull($t->fresh()->dikirim_at);
        $this->assertDatabaseHas('riwayat_kirim', ['tamu_id' => $t->id, 'jenis' => 'undangan']);

        $this->komponen($user, $u->id)->call('kirim', $asing->id)->assertNotFound();
        $this->assertNull($asing->fresh()->dikirim_at);
    }

    public function test_kirim_pengingat_tidak_mengubah_status_dikirim_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, 'Pak Budi');

        $this->komponen($user, $u->id)->call('kirim', $t->id, 'pengingat');
        $this->assertNull($t->fresh()->dikirim_at);
        $this->assertDatabaseHas('riwayat_kirim', ['tamu_id' => $t->id, 'jenis' => 'pengingat']);
    }

    public function test_draf_tidak_bisa_mencatat(): void
    {
        $user = User::factory()->create();
        $draf = Undangan::factory()->create(['user_id' => $user->id, 'slug' => 'masih-draf']);
        $t = $this->tamu($draf, 'Pak Budi');

        $this->komponen($user, $draf->id)->assertSee('Terbitkan dulu')->call('kirim', $t->id);
        $this->assertDatabaseCount('riwayat_kirim', 0);
    }

    public function test_layanan_menolak_pasangan_pengguna_undangan_tamu_yang_tidak_cocok(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $tamuLain = $this->tamu($lain, 'Asing');

        $layanan = app(CatatPengiriman::class);
        $this->assertNull($layanan->catat($user, $lain, $tamuLain));
        $this->assertNull($layanan->catat($user, $u, $tamuLain));
        $this->assertDatabaseCount('riwayat_kirim', 0);
    }

    public function test_tautan_wa_baris_memuat_nomor_dan_pesan_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, 'Pak Budi', ['hp' => '628555000111']);

        $html = html_entity_decode($this->komponen($user, $u->id)->html());
        $this->assertStringContainsString('https://wa.me/628555000111?text=', $html);
        $this->assertStringContainsString(rawurlencode(route('undangan.publik', 'raka-sinta').'?to='.$t->kode), $html);
    }

    public function test_ekspor_csv_aman_dari_injeksi_rumus(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $a = $this->tamu($u, '=HYPERLINK("http://jahat")', ['dikirim_at' => now()]);
        $this->log($u, $a);
        $this->tamu($u, 'Budi Biasa');

        $res = $this->komponen($user, $u->id)->call('ekspor');
        $res->assertFileDownloaded('riwayat-kirim-raka-sinta-'.now()->format('Ymd').'.csv');
        $isi = $res->effects['download']['content'] ?? '';
        $isi = base64_decode($isi);

        $this->assertStringContainsString('Penerima,"Nomor WhatsApp",Jenis,Kanal,Waktu,Status', $isi);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://jahat"")"', $isi);
        $this->assertStringNotContainsString("\n=HYPERLINK", $isi);
        $this->assertStringContainsString('Budi Biasa', $isi);
        $this->assertStringContainsString('Belum dikirim', $isi);
    }

    public function test_ekspor_kosong_tidak_mengunduh(): void
    {
        $user = User::factory()->create();

        $this->komponen($user, $this->terbit($user)->id)->call('ekspor')->assertNoFileDownloaded();
    }

    public function test_ringkasan_profil_menghitung_pesan_milik_sendiri(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, 'A');
        $this->log($u, $t);
        $this->log($u, $t);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $this->log($lain, $this->tamu($lain, 'B'));

        $this->assertSame(2, app(RingkasanAkun::class)->jumlahPesanTerkirim($user));
    }

    public function test_riwayat_ikut_terhapus_bersama_tamu_dan_undangan(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, 'A');
        $this->log($u, $t);

        $t->delete();
        $this->assertDatabaseCount('riwayat_kirim', 0);
    }
}
