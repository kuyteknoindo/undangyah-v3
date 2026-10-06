<?php

namespace Tests\Feature;

use App\Livewire\Bagikan;
use App\Models\RiwayatKirim;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\TemplatePesan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\KodeQr;
use App\Services\PesanUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class BagikanTest extends TestCase
{
    use RefreshDatabase;

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(Bagikan::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    private function terbit(User $user, string $slug = 'raka-sinta'): Undangan
    {
        return Undangan::factory()->terbit()->create(['user_id' => $user->id, 'slug' => $slug, 'judul' => 'Raka & Sinta']);
    }

    private function tamu(Undangan $u, array $atribut = []): Tamu
    {
        return Tamu::factory()->create(array_merge(['undangan_id' => $u->id], $atribut));
    }

    public function test_tamu_diarahkan_ke_masuk_dan_halaman_asli_bukan_placeholder(): void
    {
        $this->get('/bagikan')->assertRedirect(route('masuk'));

        $user = User::factory()->create();
        $this->terbit($user);
        $this->actingAs($user)->get('/bagikan')->assertOk()->assertSeeLivewire(Bagikan::class);
        $this->assertSame('/bagikan', route('bagikan', absolute: false));
    }

    public function test_keadaan_kosong_tanpa_undangan(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada undangan')->assertSet('undanganId', null);
    }

    public function test_undangan_awal_terbit_terbaru_dan_param_query_dibaca(): void
    {
        $user = User::factory()->create();
        $lama = $this->terbit($user, 'lama');
        $draf = Undangan::factory()->create(['user_id' => $user->id, 'slug' => 'draf-baru']);
        $baru = $this->terbit($user, 'baru');
        $baru->forceFill(['diterbitkan_at' => now()->addMinute()])->save();

        $this->komponen($user)->assertSet('undanganId', $baru->id);
    }

    public function test_undangan_orang_lain_404(): void
    {
        $user = User::factory()->create();
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');

        $this->actingAs($user)->get('/bagikan?undangan='.$lain->id)->assertNotFound();
        $this->actingAs($user)->get('/bagikan?undangan=abc')->assertNotFound();
        $this->actingAs($user)->get('/bagikan?undangan[]=1')->assertNotFound();
    }

    public function test_pilih_undangan_orang_lain_ditolak_dan_id_terkunci(): void
    {
        $user = User::factory()->create();
        $ini = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');

        $this->komponen($user)->assertSet('undanganId', $ini->id)->call('pilih', $lain->id)->assertNotFound();
        $this->assertSame($ini->id, $this->komponen($user)->get('undanganId'));

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->set('undanganId', $lain->id);
    }

    public function test_menampilkan_link_qr_dan_hanya_tamu_milik_undangan_ini(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => 'Bu Ani']);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $this->tamu($lain, ['nama' => 'Tamu Orang Lain']);
        $this->tamu($this->terbit($user, 'kedua'), ['nama' => 'Tamu Undangan Kedua']);

        $c = $this->komponen($user, $u->id);
        $c->assertSee('Bu Ani')->assertDontSee('Tamu Orang Lain')->assertDontSee('Tamu Undangan Kedua');
        $c->assertSee(parse_url(url('/'), PHP_URL_HOST).'/raka-sinta');
        $c->assertSeeHtml(KodeQr::svg(route('undangan.publik', 'raka-sinta'), 240));
    }

    public function test_draf_menampilkan_peringatan_dan_tidak_bisa_mencatat_kirim(): void
    {
        $user = User::factory()->create();
        $draf = Undangan::factory()->create(['user_id' => $user->id, 'slug' => 'masih-draf']);
        $t = $this->tamu($draf);

        $c = $this->komponen($user, $draf->id)->assertSee('belum diterbitkan')->assertSee('Terbitkan dulu');
        $c->call('catatKirim', $t->id);
        $this->assertNull($t->fresh()->dikirim_at);
        $this->assertDatabaseCount('riwayat_kirim', 0);
    }

    public function test_tautan_wa_memuat_nomor_nama_dan_tautan_pribadi_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, ['nama' => 'Pak Budi', 'hp' => '628123456789']);

        $html = $this->komponen($user, $u->id)->html();
        $this->assertStringContainsString('https://wa.me/628123456789?text=', html_entity_decode($html));
        $this->assertStringContainsString(rawurlencode('Pak Budi'), html_entity_decode($html));
        $this->assertStringContainsString(rawurlencode(route('undangan.publik', 'raka-sinta').'?to='.$t->kode), html_entity_decode($html));
    }

    public function test_catat_kirim_menandai_tamu_dan_menulis_riwayat(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, ['nama' => 'Pak Budi', 'hp' => '628123456789']);

        $this->komponen($user, $u->id)->call('catatKirim', $t->id);

        $this->assertNotNull($t->fresh()->dikirim_at);
        $this->assertDatabaseHas('riwayat_kirim', [
            'user_id' => $user->id, 'undangan_id' => $u->id, 'tamu_id' => $t->id, 'nama' => 'Pak Budi', 'hp' => '628123456789',
            'jenis' => 'undangan', 'kanal' => 'whatsapp', 'status' => 'dibuka',
        ]);
    }

    public function test_catat_kirim_tamu_orang_lain_atau_undangan_lain_404(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $kedua = $this->terbit($user, 'kedua');
        $asing = $this->tamu($this->terbit(User::factory()->create(), 'milik-lain'));
        $sebelah = $this->tamu($kedua);

        $c = $this->komponen($user, $u->id);
        $c->call('catatKirim', $asing->id)->assertNotFound();
        $c->call('catatKirim', $sebelah->id)->assertNotFound();
        $this->assertNull($asing->fresh()->dikirim_at);
        $this->assertNull($sebelah->fresh()->dikirim_at);
        $this->assertDatabaseCount('riwayat_kirim', 0);
    }

    public function test_pencatatan_dibatasi_laju(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u);
        RateLimiter::clear('catat-kirim:'.$user->id);

        $c = $this->komponen($user, $u->id);
        for ($i = 0; $i < 125; $i++) {
            $c->call('catatKirim', $t->id);
        }
        $this->assertSame(120, RiwayatKirim::count());
    }

    public function test_antrean_kirim_berikutnya_maju_dan_bisa_dilewati(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $a = $this->tamu($u, ['nama' => 'Tamu A']);
        $b = $this->tamu($u, ['nama' => 'Tamu B']);
        $this->tamu($u, ['nama' => 'Tamu C', 'dikirim_at' => now()]);

        $c = $this->komponen($user, $u->id)->assertSeeHtml('Kirim berikutnya');
        $this->assertStringContainsString('Tamu A', $c->html());
        $c->call('catatKirim', $a->id);
        $c->call('lewati', $b->id)->assertSee('Antrean selesai');
        $c->call('ulangiAntrean')->assertSeeHtml('Kirim berikutnya');
    }

    public function test_antrean_dibatasi_ke_tamu_terpilih(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => 'Tamu A']);
        $b = $this->tamu($u, ['nama' => 'Tamu B']);

        $html = $this->komponen($user, $u->id)->set('sel', [$b->id])->html();
        $this->assertMatchesRegularExpression('/Kirim berikutnya[^<]*\(dari 1 tamu terpilih\)/', $html);
        $this->assertMatchesRegularExpression('/text-sm leading-5 font-semibold text-ink">Tamu B</', $html);
    }

    public function test_lewati_id_tamu_orang_lain_diabaikan(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $asing = $this->tamu($this->terbit(User::factory()->create(), 'milik-lain'));

        $this->komponen($user, $u->id)->call('lewati', $asing->id)->assertSet('dilewati', []);
    }

    public function test_tidak_ada_formulir_tambah_tamu_dan_ada_tautan_ke_rsvp(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $c = $this->komponen($user, $u->id)->assertDontSee('Tambah Tamu')->assertSee('Tambah tamu');
        $this->assertStringContainsString(route('rsvp', ['undangan' => $u->id]), html_entity_decode($c->html()));
        $this->assertFalse(method_exists(Bagikan::class, 'tambahTamu'));
    }

    public function test_nama_tamu_di_escape(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => '<script>alert(1)</script>']);

        $this->komponen($user, $u->id)->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_pencarian_grup_dan_status_dibuka(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        // dikirim_at diisi agar kartu antrean "Kirim berikutnya" tidak ikut menampilkan nama.
        $this->tamu($u, ['nama' => 'Ani Wijaya', 'grup' => 'Keluarga', 'hp' => '628111111111', 'dikirim_at' => now()]);
        $b = $this->tamu($u, ['nama' => 'Budi Kantor', 'grup' => 'Kantor', 'hp' => '628222222222', 'dikirim_at' => now()]);
        $c2 = $this->tamu($u, ['nama' => 'Citra Kantor', 'grup' => 'Kantor', 'dikirim_at' => now()]);
        Tayangan::create(['undangan_id' => $u->id, 'tamu_id' => $b->id]);
        Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => $c2->id, 'nama' => 'Citra Kantor', 'status' => 'hadir', 'jumlah' => 1]);

        $c = $this->komponen($user, $u->id);
        $c->set('q', 'wijaya')->assertSee('Ani Wijaya')->assertDontSee('Budi Kantor');
        $c->set('q', '2222')->assertSee('Budi Kantor')->assertDontSee('Ani Wijaya');
        $c->set('q', '')->set('grup', 'Kantor')->assertSee('Budi Kantor')->assertSee('Citra Kantor')->assertDontSee('Ani Wijaya');
        $c->set('grup', '')->call('aturStatus', 'dibuka')->assertSee('Budi Kantor')->assertSee('Citra Kantor')->assertDontSee('Ani Wijaya');
        $c->call('aturStatus', 'belum')->assertSee('Ani Wijaya')->assertDontSee('Budi Kantor');
        $c->set('q', 'tidak-ada-nama-ini')->assertSee('Tamu tidak ditemukan');
        $c->call('resetFilter')->assertSet('status', 'semua')->assertSee('Ani Wijaya');
    }

    public function test_pencarian_menganggap_karakter_like_sebagai_teks_biasa(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => 'Ani', 'dikirim_at' => now()]);
        $this->tamu($u, ['nama' => '100% Budi', 'dikirim_at' => now()]);

        $this->komponen($user, $u->id)->set('q', '%')->assertSee('100% Budi')->assertDontSee('Ani');
    }

    public function test_tampilkan_lebih_banyak_dan_pilih_semua_tampil(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        Tamu::factory()->count(20)->create(['undangan_id' => $u->id]);

        $c = $this->komponen($user, $u->id)->assertSee('Menampilkan 15 dari 20 tamu');
        $c->call('pilihSemuaTampil');
        $this->assertCount(15, $c->get('sel'));
        $c->call('pilihSemuaTampil');
        $this->assertCount(0, $c->get('sel'));
        $c->call('lagi')->assertSee('Menampilkan 20 dari 20 tamu');
    }

    public function test_template_tamu_disimpan_untuk_tamu_terpilih(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $a = $this->tamu($u);
        $b = $this->tamu($u);
        $milik = TemplatePesan::create(['user_id' => $user->id, 'nama' => 'Keluarga', 'kanal' => 'whatsapp', 'isi' => 'Halo {nama} {link}']);

        $c = $this->komponen($user, $u->id)->set('sel', [$a->id, $b->id])->call('bukaTemplateTamu')->assertSet('dialogTamu', true);
        $c->set('mTpl', 'u-'.$milik->id)->set('mSalam', 'islam')->call('simpanTemplateTamu')->assertHasNoErrors()->assertSet('dialogTamu', false);

        foreach ([$a, $b] as $t) {
            $this->assertSame('u-'.$milik->id, $t->fresh()->template_pesan);
            $this->assertSame('islam', $t->fresh()->salam);
        }
    }

    public function test_template_tamu_memengaruhi_isi_tautan_wa(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, ['nama' => 'Pak Budi', 'hp' => '628123456789', 'template_pesan' => 'b-santai', 'salam' => 'islam']);

        $html = html_entity_decode($this->komponen($user, $u->id)->html());
        $this->assertStringContainsString(rawurlencode('Hai Pak Budi, kami mau berbagi kabar bahagia.'), $html);
        $this->assertStringContainsString(rawurlencode("Assalamu'alaikum Warahmatullahi Wabarakatuh,"), $html);
        $this->assertNotSame('', $t->kode);
    }

    public function test_template_tamu_tidak_valid_ditolak(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u);
        $punyaLain = TemplatePesan::create(['user_id' => User::factory()->create()->id, 'nama' => 'Orang Lain', 'kanal' => 'whatsapp', 'isi' => 'rahasia {link}']);
        $email = TemplatePesan::create(['user_id' => $user->id, 'nama' => 'Surel', 'kanal' => 'email', 'subjek' => 's', 'isi' => 'x {link}']);

        $c = $this->komponen($user, $u->id)->call('bukaTemplateTamu', $t->id);
        $c->set('mTpl', 'u-'.$punyaLain->id)->call('simpanTemplateTamu')->assertHasErrors('mTpl');
        $c->set('mTpl', 'u-'.$email->id)->call('simpanTemplateTamu')->assertHasErrors('mTpl');
        $c->set('mTpl', 'ngawur')->call('simpanTemplateTamu')->assertHasErrors('mTpl');
        $c->set('mTpl', '')->set('mSalam', 'dukun')->call('simpanTemplateTamu')->assertHasErrors('mSalam');
        $this->assertNull($t->fresh()->template_pesan);
    }

    public function test_template_tamu_orang_lain_tidak_bisa_diubah(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $asing = $this->tamu($this->terbit(User::factory()->create(), 'milik-lain'));

        $c = $this->komponen($user, $u->id)->call('bukaTemplateTamu', $asing->id)->assertSet('dialogTamu', false);
        $c->call('simpanTemplateTamu')->assertNotFound();
        $this->assertNull($asing->fresh()->template_pesan);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user, $u->id)->set('modalIds', [$asing->id]);
    }

    public function test_simpan_default_dan_validasinya(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $milik = TemplatePesan::create(['user_id' => $user->id, 'nama' => 'Punyaku', 'kanal' => 'whatsapp', 'isi' => 'Hai {nama} {link}']);

        $c = $this->komponen($user, $u->id);
        $c->set('pTemplate', 'u-'.$milik->id)->set('pSalam', 'hindu')->call('simpanDefault')->assertHasNoErrors();
        $this->assertSame(['template' => 'u-'.$milik->id, 'salam' => 'hindu'], app(PesanUndangan::class)->pengaturan($user));

        $c->set('pTemplate', 'nggak-ada')->call('simpanDefault')->assertHasErrors('pTemplate');
        $c->set('pTemplate', 'b-formal')->set('pSalam', 'xx')->call('simpanDefault')->assertHasErrors('pSalam');
        $this->assertSame('u-'.$milik->id, app(PesanUndangan::class)->pengaturan($user)['template']);
    }

    public function test_default_tidak_bisa_memakai_template_orang_lain(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $lain = TemplatePesan::create(['user_id' => User::factory()->create()->id, 'nama' => 'Orang Lain', 'kanal' => 'whatsapp', 'isi' => 'x {link}']);

        $this->komponen($user, $u->id)->set('pTemplate', 'u-'.$lain->id)->call('simpanDefault')->assertHasErrors('pTemplate');
        $this->assertSame('b-formal', app(PesanUndangan::class)->pengaturan($user)['template']);
    }

    public function test_tab_pesan_menampilkan_pratinjau_dengan_nama_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => 'Bu Rina']);

        $this->komponen($user, $u->id)->call('ubahTab', 'pesan')->assertSee('Contoh untuk Bu Rina')->assertSee('Kepada Yth. Bu Rina,')
            ->call('ubahTab', 'ngawur')->assertSet('tab', 'tamu');
    }

    public function test_qr_tamu_berisi_tautan_pribadi_dan_orang_lain_404(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, ['nama' => 'Pak Budi']);
        $asing = $this->tamu($this->terbit(User::factory()->create(), 'milik-lain'));

        $c = $this->komponen($user, $u->id)->call('bukaQr', $t->id)->assertSet('dialogQr', true)->assertSee('Kode QR Pak Budi');
        $c->assertSeeHtml(KodeQr::svg(route('undangan.publik', 'raka-sinta').'?to='.$t->kode, 240));
        $this->komponen($user, $u->id)->call('bukaQr', $asing->id)->assertNotFound();
    }

    public function test_link_ke_halaman_lain_muncul_bila_rute_ada(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $html = $this->komponen($user, $u->id)->html();
        $this->assertStringContainsString(route('riwayat-kirim', ['undangan' => $u->id]), html_entity_decode($html));
    }
}
