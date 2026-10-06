<?php

namespace Tests\Feature;

use App\Livewire\PusatBantuan;
use App\Models\BantuanArtikel;
use App\Models\BantuanSuara;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\TeksBantuan;
use Database\Seeders\BantuanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class BantuanTest extends TestCase
{
    use RefreshDatabase;

    private function artikel(array $o = []): BantuanArtikel
    {
        static $n = 0;
        $n++;

        return BantuanArtikel::create($o + ['kategori' => 'undangan', 'judul' => "Judul $n", 'isi' => "Isi $n", 'urutan' => $n, 'publik' => true]);
    }

    public function test_tamu_diarahkan_ke_masuk_dan_pengguna_melihat_halaman(): void
    {
        $this->get('/bantuan')->assertRedirect(route('masuk'));
        $this->seed(BantuanSeeder::class);

        $this->actingAs(User::factory()->create())->get('/bantuan')
            ->assertOk()->assertSee('Ada yang bisa kami bantu?')->assertSee('Bagaimana cara membuat undangan baru?')
            ->assertSee('Menampilkan 10 dari 10 pertanyaan');
    }

    public function test_seeder_idempoten_dan_tidak_menimpa_suntingan(): void
    {
        $this->seed(BantuanSeeder::class);
        BantuanArtikel::where('kode', 'f1')->update(['judul' => 'Diubah admin']);
        $this->seed(BantuanSeeder::class);

        $this->assertSame(10, BantuanArtikel::count());
        $this->assertSame('Diubah admin', BantuanArtikel::where('kode', 'f1')->value('judul'));
    }

    public function test_artikel_pertama_terbuka_dan_bisa_buka_tutup(): void
    {
        $a = $this->artikel(['judul' => 'Satu', 'isi' => 'Jawaban satu']);
        $b = $this->artikel(['judul' => 'Dua', 'isi' => 'Jawaban dua']);

        Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)
            ->assertSee('Jawaban satu')->assertDontSee('Jawaban dua')
            ->call('ubahBuka', $b->id, false)->assertSee('Jawaban dua')->assertDontSee('Jawaban satu')
            ->call('ubahBuka', $b->id, true)->assertDontSee('Jawaban dua')->assertDontSee('Jawaban satu');
    }

    public function test_pencarian_semua_kata_harus_cocok_dan_kategori_menyaring(): void
    {
        $this->artikel(['judul' => 'Ganti kata sandi', 'isi' => 'Lewat email', 'kategori' => 'akun']);
        $this->artikel(['judul' => 'Bayar paket', 'isi' => 'Transfer bank', 'kategori' => 'bayar']);
        $this->artikel(['judul' => 'Transfer ulang', 'isi' => 'Kata sandi lain', 'kategori' => 'akun']);

        Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)
            ->set('cari', 'kata SANDI')
            ->assertSee('2 hasil untuk “kata SANDI”')->assertSee('Ganti kata sandi')->assertDontSee('Bayar paket')
            ->set('cari', 'transfer sandi')->assertSee('1 hasil')->assertSee('Transfer ulang')
            ->set('cari', 'tidak-ada-kata-ini')->assertSee('Jawaban belum ditemukan')
            ->call('aturUlang')->assertSee('Bayar paket')
            ->call('pilihKategori', 'bayar')->assertSee('Bayar paket')->assertDontSee('Ganti kata sandi')
            ->call('pilihKategori', 'sembarang')->assertSee('Ganti kata sandi');
    }

    public function test_pencarian_diberi_batas_panjang(): void
    {
        Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)
            ->set('cari', str_repeat('a', 500))->assertSet('cari', str_repeat('a', 120));
    }

    public function test_artikel_tidak_publik_tidak_tampil(): void
    {
        $this->artikel(['judul' => 'Rahasia internal', 'publik' => false]);

        Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)->assertDontSee('Rahasia internal');
    }

    public function test_suara_dicatat_diubah_dan_dibatalkan_per_pengguna(): void
    {
        $a = $this->artikel();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Livewire::actingAs($u1)->test(PusatBantuan::class)
            ->call('suara', $a->id, 'ya')->assertSee('Terima kasih, senang bisa membantu.')
            ->call('suara', $a->id, 'tidak')->assertSee('Terima kasih masukannya.')->assertSee('Hubungi layanan');
        $this->assertSame(1, BantuanSuara::count());
        $this->assertFalse(BantuanSuara::first()->membantu);

        Livewire::actingAs($u2)->test(PusatBantuan::class)->assertSee('Apakah ini membantu?')->call('suara', $a->id, 'ya');
        $this->assertSame(2, BantuanSuara::count());

        Livewire::actingAs($u1)->test(PusatBantuan::class)->call('suara', $a->id, 'batal')->assertSee('Apakah ini membantu?');
        $this->assertSame(1, BantuanSuara::count());
        $this->assertSame($u2->id, BantuanSuara::first()->user_id);
    }

    public function test_suara_ditolak_untuk_nilai_ngawur_artikel_tak_publik_atau_tak_ada(): void
    {
        $a = $this->artikel();
        $tersembunyi = $this->artikel(['publik' => false]);

        Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)
            ->call('suara', $a->id, 'mungkin')->call('suara', $tersembunyi->id, 'ya')->call('suara', 99999, 'ya');

        $this->assertSame(0, BantuanSuara::count());
    }

    public function test_suara_dibatasi_laju(): void
    {
        $a = $this->artikel();
        $user = User::factory()->create();
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('bantuan-suara:'.$user->id, 60);
        }

        Livewire::actingAs($user)->test(PusatBantuan::class)->call('suara', $a->id, 'ya')->assertDispatched('toast');
        $this->assertSame(0, BantuanSuara::count());
    }

    public function test_isi_dirender_aman_tanpa_html_mentah(): void
    {
        $this->artikel(['judul' => 'Aman', 'isi' => "Baris <script>alert(1)</script> **tebal**\nbaris dua\n\n- satu\n- <b>dua</b>"]);

        Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>dua</b>', false)
            ->assertSee('&lt;script&gt;', false)->assertSee('<strong>tebal</strong>', false)->assertSee('<li>satu</li>', false);
    }

    public function test_teks_bantuan_unit(): void
    {
        $this->assertSame('', (string) TeksBantuan::html("  \n"));
        $this->assertSame('<p>a<br>b</p><p>c</p>', (string) TeksBantuan::html("a\nb\n\nc"));
        $this->assertSame('<p>&quot;x&quot; &amp; &lt;i&gt;</p>', (string) TeksBantuan::html('"x" & <i>'));
    }

    public function test_tautan_artikel_hanya_ke_rute_yang_ada(): void
    {
        $this->artikel(['judul' => 'Ada', 'rute' => 'billing', 'rute_label' => 'Buka Paket & Billing']);
        $this->artikel(['judul' => 'Hilang', 'rute' => 'rute-tidak-ada', 'rute_label' => 'Tautan hantu']);

        $c = Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)->assertSee('Buka Paket &amp; Billing', false);
        $c->call('ubahBuka', BantuanArtikel::where('judul', 'Hilang')->value('id'), false)->assertDontSee('Tautan hantu');
    }

    public function test_kontak_tampil_hanya_bila_diisi_pemilik(): void
    {
        $this->artikel();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(PusatBantuan::class)
            ->assertDontSee('Chat WhatsApp')->assertDontSee('mailto:', false)->assertDontSee('Jam layanan')->assertSee('Kirim pesan');

        Pengaturan::simpan('kontak', ['whatsapp' => '+62 812-3456-7890', 'email' => 'bantuan@contoh.test', 'jam_layanan' => '08.00 - 17.00 WIB', 'balasan' => '2 jam']);

        Livewire::actingAs($user)->test(PusatBantuan::class)
            ->assertSee('Chat WhatsApp')->assertSee('https://wa.me/6281234567890?text=', false)->assertSee('mailto:bantuan@contoh.test', false)
            ->assertSee('Jam layanan 08.00 - 17.00 WIB')->assertSee('membalas dalam 2 jam');
    }

    public function test_kontak_tidak_valid_diabaikan(): void
    {
        $this->artikel();
        Pengaturan::simpan('kontak', ['whatsapp' => '12', 'email' => 'bukan-email"><script>', 'jam_layanan' => '<b>x</b>']);

        Livewire::actingAs(User::factory()->create())->test(PusatBantuan::class)
            ->assertDontSee('Chat WhatsApp')->assertDontSee('<script>', false)->assertDontSee('<b>x</b>', false);
    }

    public function test_pencarian_lewat_query_string_dari_halaman_404(): void
    {
        $this->artikel(['judul' => 'Soal sandi']);
        $this->artikel(['judul' => 'Soal bayar']);

        $this->actingAs(User::factory()->create())->get('/bantuan?q=sandi')
            ->assertOk()->assertSee('Soal sandi')->assertDontSee('Soal bayar');
    }
}
