<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Tema;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\KodeQr;
use App\Support\SkemaUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UndanganPublikTest extends TestCase
{
    use RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private function bagian(): array
    {
        return ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];
    }

    /** Undangan terbit dengan salinan terbitan lengkap; draf berisi nama lain agar kebocoran draf terlihat. */
    private function terbit(?User $pemilik = null, array $isiTerbit = [], array $atribut = []): Undangan
    {
        $pemilik ??= User::factory()->create(['phone' => '081234567890']);
        $tema = Tema::factory()->create();
        $isi = array_replace(SkemaUndangan::isiAwal(), ['priaP' => 'Pria Terbit'], $isiTerbit);

        return Undangan::factory()->for($pemilik, 'pengguna')->dengan($tema)->create(array_merge([
            'judul' => 'Raka & Sinta',
            'slug' => 'raka-sinta',
            'status' => Undangan::TERBIT,
            'diterbitkan_at' => now(),
            'isi' => array_replace($isi, ['priaP' => 'Pria Draf']),
            'bagian' => $this->bagian(),
            'terbitan' => [
                'judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'isi' => $isi, 'bagian' => $this->bagian(),
                'pengaturan' => [], 'tema' => $tema->only(['kode', 'nama', 'tier', 'warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks', 'font_judul']),
                'diterbitkan_at' => now()->toIso8601String(),
            ],
        ], $atribut));
    }

    private function draf(?User $pemilik = null): Undangan
    {
        return Undangan::factory()->for($pemilik ?? User::factory()->create(), 'pengguna')
            ->create(['slug' => 'masih-draf', 'isi' => ['priaP' => 'Rahasia Draf'], 'bagian' => $this->bagian()]);
    }

    private function buka(string $url, array $server = []): TestResponse
    {
        return $this->withHeaders(array_merge(['User-Agent' => self::UA], $server))->get($url);
    }

    private function rsvp(Undangan $u, array $data = [], string $ua = self::UA): TestResponse
    {
        return $this->withHeaders(['User-Agent' => $ua])->postJson('/'.$u->slug.'/rsvp', array_merge(['nama' => 'Budi', 'status' => 'hadir', 'jumlah' => 2, 'pesan' => ''], $data));
    }

    private function ucapan(Undangan $u, array $data = []): TestResponse
    {
        return $this->postJson('/'.$u->slug.'/ucapan', array_merge(['nama' => 'Budi', 'pesan' => 'Selamat menempuh hidup baru'], $data));
    }

    // ------------------------------------------------------------------ tampil

    public function test_undangan_terbit_tampil_untuk_siapa_saja_dengan_header_keamanan(): void
    {
        $u = $this->terbit();

        $res = $this->buka('/raka-sinta')->assertOk()
            ->assertSee('class="uy-undangan"', false)
            ->assertSee('Pria Terbit')
            ->assertSee('data-uy-aksi="buka"', false)
            ->assertSee('data-uy-bagian="rsvp"', false)
            ->assertSee('/undangan/tamu.js', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-cache', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString("script-src 'self'", (string) $res->headers->get('Content-Security-Policy'));
        // Tanpa layout aplikasi (sidebar/Livewire).
        $res->assertDontSee('livewire', false)->assertDontSee('Beranda');
        $this->assertSame(route('undangan.publik', 'raka-sinta'), url('/raka-sinta'));
        $this->assertNotNull($u->id);
    }

    public function test_draf_dan_alamat_tak_dikenal_404_ramah(): void
    {
        $this->draf();

        $this->buka('/masih-draf')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertDontSee('Rahasia Draf');
        $this->buka('/tidak-ada-undangan')->assertNotFound()->assertSee('Halaman tidak ditemukan');
        $this->postJson('/masih-draf/rsvp', ['nama' => 'Budi', 'status' => 'hadir'])->assertNotFound();
        $this->postJson('/masih-draf/ucapan', ['nama' => 'Budi', 'pesan' => 'Halo'])->assertNotFound();
        $this->getJson('/masih-draf/ucapan')->assertNotFound();
    }

    public function test_pemilik_membuka_alamat_draf_miliknya_diarahkan_ke_pratinjau_orang_lain_404(): void
    {
        $pemilik = User::factory()->create();
        $u = $this->draf($pemilik);

        $this->actingAs($pemilik)->get('/masih-draf')->assertRedirect(route('pratinjau', ['undangan' => $u->id]));
        $this->actingAs(User::factory()->create())->get('/masih-draf')->assertNotFound();
    }

    public function test_status_terbit_tanpa_salinan_terbitan_tidak_membocorkan_draf(): void
    {
        $u = $this->terbit();
        $u->update(['terbitan' => ['judul' => 'x']]);

        $this->buka('/raka-sinta')->assertNotFound()->assertDontSee('Pria Draf');
    }

    public function test_alamat_bukan_slug_valid_dan_huruf_besar(): void
    {
        $this->terbit();

        $this->buka('/ab')->assertNotFound();                 // terlalu pendek
        $this->buka('/raka--sinta')->assertNotFound();        // tanda hubung ganda
        $this->buka('/-raka')->assertNotFound();
        $this->buka('/'.str_repeat('a', 61))->assertNotFound();
        $this->buka('/RAKA-Sinta?to=abc')->assertStatus(301)->assertRedirect('/raka-sinta?to=abc');
    }

    public function test_slug_rute_aplikasi_tidak_tertangkap(): void
    {
        // Undangan nakal dengan slug yang sama dengan rute aplikasi (seharusnya ditolak SlugUndangan, di sini dipaksa).
        foreach (['masuk', 'daftar', 'beranda', 'rsvp', 'offline', 'akun'] as $kata) {
            $this->terbit(null, [], ['slug' => $kata]);
        }

        $this->get('/masuk')->assertOk()->assertDontSee('Pria Terbit');
        $this->get('/daftar')->assertOk()->assertDontSee('Pria Terbit');
        $this->get('/offline')->assertOk()->assertDontSee('Pria Terbit');
        $this->get('/beranda')->assertRedirect(route('masuk'));
        $this->get('/akun')->assertRedirect(route('masuk'));
        $this->get('/rsvp')->assertRedirect(route('masuk'));
    }

    // ------------------------------------------------------------------ ?to=

    public function test_kode_tamu_valid_menyapa_tamu_dan_terhubung_ke_undangan_itu_saja(): void
    {
        $u = $this->terbit();
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id, 'nama' => 'Bapak Haryanto', 'jumlah' => 3]);

        $res = $this->buka('/raka-sinta?to='.$tamu->kode)->assertOk()->assertSee('Bapak Haryanto')->assertSee($tamu->kode);
        $this->assertStringContainsString('"kode":"'.$tamu->kode.'"', $res->getContent());
        $this->assertStringContainsString('"maksJumlah":3', $res->getContent());
        $this->assertStringContainsString('<title>Raka &amp; Sinta · untuk Bapak Haryanto</title>', $res->getContent());
    }

    public function test_nama_tamu_tampil_di_sampul_dan_qr_sungguhan_hanya_untuk_tamu_terdaftar(): void
    {
        $u = $this->terbit();
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id, 'nama' => 'Ibu Wulandari']);

        $res = $this->buka('/raka-sinta?to='.$tamu->kode)->assertOk();
        $this->assertMatchesRegularExpression('/uy-tamu-sampul[^>]*>Ibu Wulandari</', $res->getContent());
        $res->assertSee('data-uy-bagian="qr"', false)->assertSee('QR Kehadiran');
        $this->assertMatchesRegularExpression('#data-uy-bagian="qr">\s*<svg[^>]*width="116"#', $res->getContent());
        // Bukan penanda tempat (viewBox 21x21 dengan 3 kotak pencari) melainkan QR sungguhan dari link pribadi tamu.
        $res->assertDontSee('viewBox="0 0 21 21"', false);
        $this->assertStringContainsString(KodeQr::svg(route('undangan.publik', 'raka-sinta').'?to='.$tamu->kode, 116), $res->getContent());

        $this->buka('/raka-sinta')->assertOk()->assertDontSee('uy-tamu-sampul', false)->assertDontSee('data-uy-bagian="qr"', false);
    }

    public function test_sapaan_nama_tanpa_kode_tamu_tidak_menampilkan_qr(): void
    {
        $this->terbit();

        $res = $this->buka('/raka-sinta?to=Pak%20Budi')->assertOk();
        $this->assertMatchesRegularExpression('/uy-tamu-sampul[^>]*>Pak Budi</', $res->getContent());
        $res->assertDontSee('data-uy-bagian="qr"', false)->assertDontSee('QR Kehadiran');
    }

    public function test_kode_asing_atau_milik_undangan_lain_diabaikan(): void
    {
        $this->terbit();
        $lain = $this->terbit(null, [], ['slug' => 'undangan-lain']);
        $asing = Tamu::factory()->create(['undangan_id' => $lain->id, 'nama' => 'Tamu Undangan Lain']);

        $this->buka('/raka-sinta?to='.$asing->kode)->assertOk()->assertDontSee('Tamu Undangan Lain')->assertDontSee($asing->kode);
        $this->buka('/raka-sinta?to=abcdefghij')->assertOk()->assertDontSee('abcdefghij');
        $this->buka('/raka-sinta?to[]=x')->assertOk();
    }

    public function test_teks_bebas_sebagai_nama_sapaan_di_escape_dan_dibatasi(): void
    {
        $this->terbit();

        $this->buka('/raka-sinta?to='.urlencode('Budi Santoso'))->assertOk()->assertSee('Budi Santoso');

        $res = $this->buka('/raka-sinta?to='.urlencode('<script>alert(1)</script>Siti <b>R</b>'))->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)', $res->getContent());
        $res->assertSee('alert(1)Siti R', false);

        $this->buka('/raka-sinta?to='.urlencode(str_repeat('Panjang ', 20)))->assertOk()->assertDontSee('Panjang Panjang Panjang');
    }

    // ------------------------------------------------------------------ RSVP

    public function test_rsvp_anonim_tersimpan_dan_pemilik_diberi_notifikasi(): void
    {
        $u = $this->terbit();

        $this->rsvp($u, ['nama' => 'Budi Santoso', 'pesan' => 'Insya Allah hadir'])
            ->assertOk()->assertJson(['ok' => true])->assertJsonPath('nama', 'Budi Santoso');

        $r = Rsvp::firstOrFail();
        $this->assertSame([$u->id, 'Budi Santoso', 'hadir', 2, null], [$r->undangan_id, $r->nama, $r->status, $r->jumlah, $r->tamu_id]);
        $this->assertSame('Insya Allah hadir', $r->pesan);
        $this->assertSame('Insya Allah hadir', Ucapan::firstOrFail()->pesan, 'pesan RSVP ikut tampil sebagai ucapan seperti di desain');

        $n = Notifikasi::where('user_id', $u->user_id)->where('tipe', 'rsvp')->firstOrFail();
        $this->assertStringContainsString('Budi Santoso', $n->judul);
        $this->assertSame('/rsvp', $n->tautan);
        $this->assertSame(0, Notifikasi::where('tipe', 'ucapan')->count());
    }

    public function test_rsvp_tidak_hadir_menyimpan_jumlah_nol(): void
    {
        $u = $this->terbit();

        $this->rsvp($u, ['status' => 'tidak', 'jumlah' => 5])->assertOk();

        $this->assertSame(0, Rsvp::firstOrFail()->jumlah);
        $this->assertSame('tidak', Rsvp::firstOrFail()->status);
    }

    public function test_rsvp_validasi_gagal(): void
    {
        $u = $this->terbit();

        $this->rsvp($u, ['nama' => ''])->assertStatus(422)->assertJsonPath('ok', false)->assertJsonPath('pesan', 'Isi nama Anda dulu.');
        $this->rsvp($u, ['nama' => str_repeat('a', 61)])->assertStatus(422)->assertJsonPath('pesan', 'Nama maksimal 60 karakter.');
        $this->rsvp($u, ['status' => 'mungkin'])->assertStatus(422)->assertJsonPath('pesan', 'Pilih hadir atau tidak hadir.');
        $this->rsvp($u, ['jumlah' => 21])->assertStatus(422)->assertJsonPath('pesan', 'Jumlah tamu maksimal 20 orang.');
        $this->rsvp($u, ['jumlah' => 0])->assertStatus(422)->assertJsonPath('pesan', 'Jumlah tamu minimal 1.');
        $this->rsvp($u, ['jumlah' => 'banyak'])->assertStatus(422);
        $this->rsvp($u, ['pesan' => str_repeat('x', 501)])->assertStatus(422)->assertJsonPath('pesan', 'Pesan maksimal 500 karakter.');
        $this->rsvp($u, ['nama' => ['array']])->assertStatus(422);

        $this->assertSame(0, Rsvp::count());
        $this->assertSame(0, Notifikasi::count());
    }

    public function test_rsvp_membersihkan_tag_html(): void
    {
        $u = $this->terbit();

        $this->rsvp($u, ['nama' => '<b>Budi</b> <img src=x onerror=alert(1)>', 'pesan' => "Halo <script>alert(1)</script>dunia\n\n\n\nbaris"])->assertOk();

        $r = Rsvp::firstOrFail();
        $this->assertSame('Budi', $r->nama);
        $this->assertStringNotContainsString('<', $r->pesan);
        $this->assertStringNotContainsString("\n\n\n", $r->pesan);
    }

    public function test_rsvp_dibatasi_per_ip_dan_undangan(): void
    {
        $u = $this->terbit();
        $lain = $this->terbit(null, [], ['slug' => 'undangan-lain']);

        foreach (range(1, 5) as $i) {
            $this->rsvp($u, ['nama' => 'Tamu '.$i])->assertOk();
        }
        $this->rsvp($u, ['nama' => 'Tamu 6'])->assertStatus(429)->assertJsonPath('ok', false)->assertHeader('Retry-After');
        $this->assertSame(5, Rsvp::count());

        // Undangan lain punya hitungan sendiri.
        $this->rsvp($lain)->assertOk();
    }

    public function test_jebakan_honeypot_pura_pura_berhasil_tanpa_menyimpan(): void
    {
        $u = $this->terbit();

        $this->rsvp($u, ['situs_web' => 'http://spam.example'])->assertOk()->assertJson(['ok' => true]);
        $this->ucapan($u, ['situs_web' => 'x'])->assertOk();

        $this->assertSame(0, Rsvp::count());
        $this->assertSame(0, Ucapan::count());
        $this->assertSame(0, Notifikasi::count());
    }

    public function test_rsvp_dengan_kode_tamu_undangan_lain_atau_palsu_ditolak(): void
    {
        $u = $this->terbit();
        $lain = $this->terbit(null, [], ['slug' => 'undangan-lain']);
        $asing = Tamu::factory()->create(['undangan_id' => $lain->id]);

        $this->rsvp($u, ['kode' => $asing->kode])->assertStatus(422)->assertJsonPath('ok', false);
        $this->rsvp($u, ['kode' => 'kodepalsu1'])->assertStatus(422);
        $this->rsvp($u, ['kode' => "x'; drop"])->assertStatus(422);
        $this->ucapan($u, ['kode' => $asing->kode])->assertStatus(422);

        $this->assertSame(0, Rsvp::count());
        $this->assertSame(0, Ucapan::count());
    }

    public function test_rsvp_tamu_terdaftar_memperbarui_bukan_menggandakan(): void
    {
        $u = $this->terbit();
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id, 'nama' => 'Bapak Haryanto', 'jumlah' => 3]);

        $this->rsvp($u, ['kode' => $tamu->kode, 'nama' => 'Pak Har', 'jumlah' => 3, 'pesan' => 'Hadir ya'])->assertOk();
        $this->rsvp($u, ['kode' => $tamu->kode, 'nama' => 'Pak Har', 'status' => 'tidak', 'pesan' => 'Hadir ya'])->assertOk();

        $this->assertSame(1, Rsvp::count());
        $r = Rsvp::firstOrFail();
        $this->assertSame([$tamu->id, $tamu->kode, 'tidak', 0], [$r->tamu_id, $r->kode_tamu, $r->status, $r->jumlah]);
        $this->assertSame(1, Ucapan::count(), 'pesan yang sama tidak dibuat ulang jadi ucapan');
        $this->assertSame(2, Notifikasi::where('tipe', 'rsvp')->count());
        $this->assertStringContainsString('diperbarui', Notifikasi::latest('id')->first()->judul);
    }

    public function test_jumlah_tamu_terdaftar_dibatasi_jatahnya(): void
    {
        $u = $this->terbit();
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id, 'jumlah' => 2]);

        $this->rsvp($u, ['kode' => $tamu->kode, 'jumlah' => 3])->assertStatus(422)->assertJsonPath('pesan', 'Jumlah tamu maksimal 2 orang.');
        $this->rsvp($u, ['kode' => $tamu->kode, 'jumlah' => 2])->assertOk();
    }

    public function test_tamu_anonim_boleh_banyak_dan_dari_undangan_lain_tidak_bocor(): void
    {
        $u = $this->terbit();
        $lain = $this->terbit(null, [], ['slug' => 'undangan-lain']);

        $this->rsvp($u, ['nama' => 'Budi'])->assertOk();
        $this->rsvp($u, ['nama' => 'Budi'])->assertOk();

        $this->assertSame(2, Rsvp::where('undangan_id', $u->id)->count());
        $this->assertSame(0, Rsvp::where('undangan_id', $lain->id)->count());
    }

    // ------------------------------------------------------------------ ucapan

    public function test_ucapan_tersimpan_tampil_di_daftar_dan_xss_di_escape(): void
    {
        $u = $this->terbit();

        $this->ucapan($u, ['nama' => 'Sari', 'pesan' => '<script>alert("xss")</script>Barakallah'])->assertOk()
            ->assertJsonPath('ucapan.nama', 'Sari')->assertJsonPath('ucapan.pesan', 'alert("xss")Barakallah');
        // Data lama yang sudah ada di basis data pun harus di-escape saat tampil.
        Ucapan::create(['undangan_id' => $u->id, 'nama' => '<img src=x onerror=alert(2)>', 'pesan' => '<b>tebal</b> & "kutip"']);

        $res = $this->buka('/raka-sinta')->assertOk()->assertSee('Barakallah');
        $html = $res->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('<b>tebal</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;tebal&lt;/b&gt; &amp; &quot;kutip&quot;', $html);

        $n = Notifikasi::where('tipe', 'ucapan')->firstOrFail();
        $this->assertSame($u->user_id, $n->user_id);
        $this->assertSame('/buku-tamu', $n->tautan);
    }

    public function test_ucapan_validasi_dan_batas(): void
    {
        $u = $this->terbit();

        $this->ucapan($u, ['pesan' => ''])->assertStatus(422)->assertJsonPath('pesan', 'Tulis ucapan Anda dulu.');
        $this->ucapan($u, ['nama' => ''])->assertStatus(422)->assertJsonPath('pesan', 'Isi nama Anda dulu.');
        $this->ucapan($u, ['pesan' => str_repeat('a', 501)])->assertStatus(422);
        $this->assertSame(0, Ucapan::count());

        foreach (range(1, 3) as $i) {
            $this->ucapan($u, ['pesan' => 'Ucapan '.$i])->assertOk();
        }
        $this->ucapan($u, ['pesan' => 'Ucapan 4'])->assertStatus(429);
        $this->assertSame(3, Ucapan::count());
    }

    public function test_ucapan_dengan_kode_tamu_terhubung(): void
    {
        $u = $this->terbit();
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id]);

        $this->ucapan($u, ['kode' => $tamu->kode])->assertOk();

        $x = Ucapan::firstOrFail();
        $this->assertSame([$tamu->id, $tamu->kode], [$x->tamu_id, $x->kode_tamu]);
    }

    public function test_daftar_ucapan_berhalaman_terbaru_dulu_dan_hanya_milik_undangan_itu(): void
    {
        $u = $this->terbit();
        $lain = $this->terbit(null, [], ['slug' => 'undangan-lain']);
        foreach (range(1, 25) as $i) {
            Ucapan::create(['undangan_id' => $u->id, 'nama' => 'Tamu '.$i, 'pesan' => 'Ucapan nomor '.$i]);
        }
        Ucapan::create(['undangan_id' => $lain->id, 'nama' => 'Orang Lain', 'pesan' => 'Bukan untuk undangan ini']);

        $halaman = $this->buka('/raka-sinta')->assertOk()->assertSee('Ucapan nomor 25')->assertSee('Ucapan nomor 16')
            ->assertDontSee('Ucapan nomor 15')->assertDontSee('Bukan untuk undangan ini');
        $this->assertStringContainsString('"adaLagi":true', $halaman->getContent());

        $dua = $this->getJson('/raka-sinta/ucapan?sebelum='.Ucapan::where('pesan', 'Ucapan nomor 16')->value('id'))->assertOk();
        $this->assertSame('Ucapan nomor 15', $dua->json('data.0.pesan'));
        $this->assertCount(10, $dua->json('data'));
        $this->assertTrue($dua->json('ada_lagi'));

        $tiga = $this->getJson('/raka-sinta/ucapan?sebelum='.$dua->json('kursor'))->assertOk();
        $this->assertCount(5, $tiga->json('data'));
        $this->assertFalse($tiga->json('ada_lagi'));
        $this->assertStringNotContainsString('Orang Lain', $tiga->getContent());
    }

    // ------------------------------------------------------------------ tayangan

    public function test_tayangan_dicatat_sekali_per_pengunjung_dalam_jendela(): void
    {
        $u = $this->terbit();

        $this->buka('/raka-sinta')->assertOk();
        $this->buka('/raka-sinta')->assertOk();
        $this->assertSame(1, Tayangan::count());

        // Pengunjung lain (agen pengguna berbeda) dihitung terpisah.
        $this->buka('/raka-sinta', ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile Safari/537.36'])->assertOk();
        $this->assertSame(2, Tayangan::where('undangan_id', $u->id)->count());
    }

    public function test_tayangan_tamu_terdaftar_membawa_kode(): void
    {
        $u = $this->terbit();
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id]);

        $this->buka('/raka-sinta?to='.$tamu->kode)->assertOk();

        $t = Tayangan::firstOrFail();
        $this->assertSame([$tamu->id, $tamu->kode], [$t->tamu_id, $t->kode_tamu]);
    }

    public function test_tayangan_mengabaikan_bot_dan_pemilik_yang_masuk(): void
    {
        $u = $this->terbit();

        foreach (['Googlebot/2.1 (+http://www.google.com/bot.html)', 'WhatsApp/2.23.20 A', 'facebookexternalhit/1.1', 'curl/8.4.0', 'Mozilla/5.0 (compatible; bingbot/2.0)', ''] as $ua) {
            $this->buka('/raka-sinta', ['User-Agent' => $ua])->assertOk();
        }
        $this->assertSame(0, Tayangan::count());

        $this->actingAs($u->pengguna)->get('/raka-sinta', ['User-Agent' => self::UA])->assertOk();
        $this->assertSame(0, Tayangan::count());

        $this->actingAs(User::factory()->create())->get('/raka-sinta', ['User-Agent' => self::UA])->assertOk();
        $this->assertSame(1, Tayangan::count());
    }

    public function test_tayangan_tidak_menyimpan_ip_dan_draf_tidak_dicatat(): void
    {
        $this->terbit();
        $this->draf();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->buka('/raka-sinta')->assertOk();
        $this->buka('/masih-draf')->assertNotFound();

        $this->assertSame(1, Tayangan::count());
        $kolom = Schema::getColumnListing('tayangan');
        $this->assertEmpty(array_intersect($kolom, ['ip', 'alamat_ip', 'user_agent']));
        $this->assertStringNotContainsString('203.0.113.9', json_encode(Tayangan::first()->toArray()));
    }

    // ------------------------------------------------------------------ meta

    public function test_meta_og_dan_twitter_dari_terbitan(): void
    {
        $this->terbit(null, [
            'scJudul' => 'Pernikahan Raka & Sinta', 'scDesc' => 'd2', 'priaP' => 'Raka', 'wanitaP' => 'Sinta', 'tglCover' => 'Minggu, 14 Februari 2027',
            '_foto' => ['share' => 'https://cdn.example.com/foto-share.jpg'],
        ]);

        $res = $this->buka('/raka-sinta')->assertOk();
        $res->assertSee('<title>Pernikahan Raka &amp; Sinta</title>', false)
            ->assertSee('<meta property="og:title" content="Pernikahan Raka &amp; Sinta">', false)
            ->assertSee('<meta property="og:description" content="Raka &amp; Sinta akan menikah pada Minggu, 14 Februari 2027. Mohon doa dan kehadiran Anda.">', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.com/foto-share.jpg">', false)
            ->assertSee('<meta property="og:url" content="'.url('/raka-sinta').'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_meta_memakai_judul_undangan_dan_gambar_bawaan_bila_foto_tidak_aman(): void
    {
        $this->terbit(null, ['_foto' => ['share' => 'javascript:alert(1)', 'cover' => 'data:text/html,<script>']]);

        $res = $this->buka('/raka-sinta')->assertOk()
            ->assertSee('<meta property="og:title" content="Raka &amp; Sinta">', false)
            ->assertSee('<meta property="og:image" content="'.url('/undangan/og-bawaan.png').'">', false);
        $this->assertStringNotContainsString('javascript:alert', $res->getContent());
        $this->assertFileExists(public_path('undangan/og-bawaan.png'));
    }

    public function test_meta_foto_sampul_dipakai_bila_foto_share_kosong_dan_deskripsi_bawaan(): void
    {
        $this->terbit(null, ['_foto' => ['cover' => '/storage/media/sampul.jpg'], 'scDesc' => 'tidak-ada']);

        $this->buka('/raka-sinta')->assertOk()
            ->assertSee('<meta property="og:image" content="'.url('/storage/media/sampul.jpg').'">', false)
            ->assertSee('<meta property="og:description" content="Anda diundang. Buka undangan digital ini.">', false);
    }

    // ------------------------------------------------------------------ privasi dan isolasi

    public function test_data_pemilik_dan_tamu_lain_tidak_bocor(): void
    {
        $u = $this->terbit();
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id, 'nama' => 'Tamu Pertama', 'hp' => '081999888777']);
        $lain = Tamu::factory()->create(['undangan_id' => $u->id, 'nama' => 'Tamu Kedua Rahasia', 'hp' => '081555444333']);

        $res = $this->buka('/raka-sinta?to='.$tamu->kode)->assertOk()->assertSee('Tamu Pertama');

        foreach ([$u->pengguna->email, '081234567890', 'Tamu Kedua Rahasia', '081999888777', '081555444333', $lain->kode] as $rahasia) {
            $res->assertDontSee($rahasia);
        }
    }

    public function test_draf_yang_diubah_tidak_mengubah_tampilan_publik_sampai_terbit_ulang(): void
    {
        $u = $this->terbit();

        $u->update(['judul' => 'Judul Draf Baru', 'isi' => array_replace((array) $u->isi, ['priaP' => 'Nama Draf Baru']), 'ada_perubahan' => true]);

        $this->buka('/raka-sinta')->assertOk()->assertSee('Pria Terbit')->assertSee('Raka &amp; Sinta', false)
            ->assertDontSee('Nama Draf Baru')->assertDontSee('Judul Draf Baru');

        // Terbit ulang: salinan terbitan diganti, baru tampil.
        $terbitan = $u->terbitan;
        $terbitan['isi']['priaP'] = 'Nama Draf Baru';
        $u->update(['terbitan' => $terbitan, 'ada_perubahan' => false]);

        $this->buka('/raka-sinta')->assertOk()->assertSee('Nama Draf Baru')->assertDontSee('Pria Terbit');
    }

    public function test_setelah_dijadikan_draf_alamat_publik_404(): void
    {
        $u = $this->terbit();
        $this->buka('/raka-sinta')->assertOk();

        $u->update(['status' => Undangan::DRAF]);

        $this->buka('/raka-sinta')->assertNotFound();
        $this->rsvp($u)->assertNotFound();
    }

    public function test_halaman_memuat_token_csrf_untuk_pengiriman(): void
    {
        $this->terbit();

        $this->buka('/raka-sinta')->assertSee('<meta name="csrf-token" content="', false);
    }
}
