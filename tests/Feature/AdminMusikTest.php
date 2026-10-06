<?php

namespace Tests\Feature;

use App\Livewire\Admin\KatalogMusik;
use App\Livewire\EditorUndangan;
use App\Models\Lagu;
use App\Models\Media;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use App\Services\IsiUndangan;
use App\Services\KatalogLagu;
use App\Services\PenyimpanMedia;
use App\Services\RenderUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Concerns\BerkasUji;
use Tests\TestCase;

class AdminMusikTest extends TestCase
{
    use BerkasUji, RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::DISK);
        Storage::fake('local');
    }

    private function halaman(?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->admin()->create())->test(KatalogMusik::class);
    }

    private function lagu(array $atribut = []): Lagu
    {
        static $n = 0;
        $n++;

        return Lagu::create(array_merge([
            'kode' => 'lt'.$n, 'judul' => 'Lagu Uji '.$n, 'artis' => 'Artis '.$n, 'kategori' => 'Pop', 'durasi' => 120, 'sumber' => 'tautan',
            'url' => 'https://cdn.example.com/lagu'.$n.'.mp3', 'status' => 'publik', 'urutan' => 100 + $n,
        ], $atribut));
    }

    private function isiForm($h, array $f = [])
    {
        $dasar = ['judul' => 'Judul Baru', 'artis' => 'Penyanyi', 'durasi' => '3:12', 'kategori' => 'Akustik', 'sumber' => 'tautan', 'url' => 'https://cdn.example.com/baru.mp3'];
        foreach (array_replace($dasar, $f) as $k => $v) {
            $h->set('f.'.$k, $v);
        }

        return $h;
    }

    // ------------------------------------------------------------------ halaman admin

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/musik')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/musik')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/musik')->assertOk()->assertSeeLivewire(KatalogMusik::class);
        Livewire::actingAs(User::factory()->create())->test(KatalogMusik::class)->assertForbidden();
    }

    public function test_menampilkan_lagu_bawaan_dan_ringkasan(): void
    {
        $this->halaman()->assertSee('Perayaan Cinta')->assertSee('Senja di Pelaminan')->assertSee('3:12')->assertSee('4:05')
            ->assertSee('5 lagu bawaan editor')->assertSee('Total lagu');
    }

    public function test_tambah_lagu_dari_link_audio(): void
    {
        $this->isiForm($this->halaman()->call('bukaBaru'), ['keterangan' => ' Piano '])->call('simpan')->assertHasNoErrors()->assertSet('dialogForm', false)->assertDispatched('toast');

        $l = Lagu::where('judul', 'Judul Baru')->firstOrFail();
        $this->assertSame(192, $l->durasi);
        $this->assertSame('Piano', $l->keterangan);
        $this->assertSame('publik', $l->status);
        $this->assertFalse($l->bawaan);
        $this->assertSame(6, $l->urutan);
        $this->assertMatchesRegularExpression('/^lg[a-z0-9]{8}$/', $l->kode);
        $this->assertSame('https://cdn.example.com/baru.mp3', $l->urlAudio());
    }

    public function test_tambah_lagu_dari_media_sistem_dan_unggah_dari_formulir(): void
    {
        $audio = app(PenyimpanMedia::class)->simpan($this->mp3('sistem.mp3'), null);
        $this->isiForm($this->halaman()->call('bukaBaru'), ['sumber' => 'media', 'media_id' => (string) $audio->id, 'judul' => 'Dari Media'])->call('simpan')->assertHasNoErrors();
        $l = Lagu::where('judul', 'Dari Media')->firstOrFail();
        $this->assertSame($audio->id, $l->media_id);
        $this->assertSame($audio->url(), $l->urlAudio());

        $h = $this->halaman()->call('bukaBaru')->set('audio', $this->mp3('unggah.mp3'))->assertHasNoErrors();
        $this->assertNotSame('', $h->get('f.media_id'));
        $baru = Media::findOrFail((int) $h->get('f.media_id'));
        $this->assertNull($baru->user_id);
        $this->assertSame('Musik', $baru->folder);

        // bukan audio ditolak
        $h2 = $this->halaman()->call('bukaBaru')->set('audio', $this->gambar('bukan.jpg'));
        $h2->assertHasErrors('f.media_id')->assertSet('f.media_id', '');
    }

    public function test_tambah_lagu_dari_youtube(): void
    {
        $this->isiForm($this->halaman()->call('bukaBaru')->call('setSumber', 'youtube'), ['sumber' => 'youtube', 'youtube' => 'https://youtu.be/dQw4w9WgXcQ'])->call('simpan')->assertHasNoErrors();
        $l = Lagu::where('judul', 'Judul Baru')->firstOrFail();
        $this->assertSame('youtube', $l->sumber);
        $this->assertNull($l->urlAudio());
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $l->urlYoutube());
    }

    public function test_validasi_formulir(): void
    {
        $h = $this->halaman()->call('bukaBaru');
        $this->isiForm($h, ['judul' => ''])->call('simpan')->assertHasErrors(['f.judul' => 'required']);
        $this->isiForm($h, ['artis' => ''])->call('simpan')->assertHasErrors(['f.artis' => 'required']);
        $this->isiForm($h, ['durasi' => '312'])->call('simpan')->assertHasErrors('f.durasi');
        $this->isiForm($h, ['durasi' => '3:75'])->call('simpan')->assertHasErrors('f.durasi');
        $this->isiForm($h, ['durasi' => '0:00'])->call('simpan')->assertHasErrors('f.durasi');
        $this->isiForm($h, ['kategori' => 'Dangdut Koplo'])->call('simpan')->assertHasErrors('f.kategori');
        $this->isiForm($h, ['url' => ''])->call('simpan')->assertHasErrors('f.url');
        $this->isiForm($h, ['url' => 'javascript:alert(1)'])->call('simpan')->assertHasErrors('f.url');
        $this->isiForm($h, ['url' => '/storage/media/x.mp3'])->call('simpan')->assertHasErrors('f.url');
        $this->isiForm($h, ['sumber' => 'youtube', 'youtube' => ''])->call('simpan')->assertHasErrors('f.youtube');
        $this->isiForm($h, ['sumber' => 'youtube', 'youtube' => 'https://vimeo.com/123'])->call('simpan')->assertHasErrors('f.youtube');
        $this->isiForm($h, ['sumber' => 'youtube', 'youtube' => 'https://evil.com/?u=youtube.com'])->call('simpan')->assertHasErrors('f.youtube');
        $this->isiForm($h, ['sumber' => 'media', 'media_id' => ''])->call('simpan')->assertHasErrors('f.media_id');
        $this->isiForm($h, ['sumber' => 'media', 'media_id' => '99999'])->call('simpan')->assertHasErrors('f.media_id');
        $this->isiForm($h, ['status' => 'rahasia'])->call('simpan')->assertHasErrors('f.status');
        $this->isiForm($h, ['judul' => str_repeat('a', 121)])->call('simpan')->assertHasErrors('f.judul');
        $this->assertSame(5, Lagu::count());
    }

    public function test_media_milik_pengguna_atau_bukan_audio_tidak_bisa_jadi_sumber(): void
    {
        $milik = app(PenyimpanMedia::class)->simpan($this->mp3('milik.mp3'), User::factory()->create());
        $gambar = app(PenyimpanMedia::class)->simpan($this->gambar('g.jpg'), null);
        $h = $this->halaman()->call('bukaBaru');
        $this->isiForm($h, ['sumber' => 'media', 'media_id' => (string) $milik->id])->call('simpan')->assertHasErrors('f.media_id');
        $this->isiForm($h, ['sumber' => 'media', 'media_id' => (string) $gambar->id])->call('simpan')->assertHasErrors('f.media_id');
        $this->assertSame(5, Lagu::count());
    }

    public function test_judul_dan_artis_kembar_ditolak_tetapi_edit_diri_sendiri_boleh(): void
    {
        $this->lagu(['judul' => 'Kembar', 'artis' => 'Duo']);
        $h = $this->halaman()->call('bukaBaru');
        $this->isiForm($h, ['judul' => 'KEMBAR', 'artis' => 'duo'])->call('simpan')->assertHasErrors('f.judul');

        $l = Lagu::where('judul', 'Kembar')->first();
        $this->halaman()->call('bukaEdit', $l->id)->set('f.keterangan', 'Edit')->call('simpan')->assertHasNoErrors();
        $this->assertSame('Edit', $l->fresh()->keterangan);
    }

    public function test_lagu_bawaan_boleh_tanpa_sumber_tetapi_lagu_baru_tidak(): void
    {
        $l1 = Lagu::where('kode', 'l1')->firstOrFail();
        $this->halaman()->call('bukaEdit', $l1->id)->assertSet('f.judul', 'Perayaan Cinta')->assertSet('f.durasi', '3:12')->set('f.artis', 'Studio Baru')->call('simpan')->assertHasNoErrors();
        $this->assertSame('Studio Baru', $l1->fresh()->artis);
        $this->assertSame('l1', $l1->fresh()->kode);
    }

    public function test_ubah_status_dan_urutan(): void
    {
        $this->halaman()->call('ubahStatus', Lagu::where('kode', 'l2')->value('id'));
        $this->assertSame('draf', Lagu::where('kode', 'l2')->value('status'));
        $this->halaman()->call('ubahStatus', Lagu::where('kode', 'l2')->value('id'));
        $this->assertSame('publik', Lagu::where('kode', 'l2')->value('status'));

        $urut = fn () => Lagu::query()->urut()->pluck('kode')->all();
        $this->assertSame(['l1', 'l2', 'l3', 'l4', 'l5'], $urut());
        $h = $this->halaman()->call('turun', Lagu::where('kode', 'l1')->value('id'));
        $this->assertSame(['l2', 'l1', 'l3', 'l4', 'l5'], $urut());
        $h->call('naik', Lagu::where('kode', 'l5')->value('id'));
        $this->assertSame(['l2', 'l1', 'l3', 'l5', 'l4'], $urut());
        // batas atas dan bawah tidak berubah; urutan tetap berurutan 1..n
        $h->call('naik', Lagu::where('kode', 'l2')->value('id'))->call('turun', Lagu::where('kode', 'l4')->value('id'));
        $this->assertSame(['l2', 'l1', 'l3', 'l5', 'l4'], $urut());
        $this->assertSame([1, 2, 3, 4, 5], Lagu::query()->urut()->pluck('urutan')->all());
    }

    public function test_urutan_tidak_bisa_diubah_saat_difilter(): void
    {
        $h = $this->halaman()->set('q', 'Perayaan')->call('turun', Lagu::where('kode', 'l1')->value('id'))->assertDispatched('toast');
        $this->assertSame(['l1', 'l2', 'l3', 'l4', 'l5'], Lagu::query()->urut()->pluck('kode')->all());
        $h->set('q', '')->call('resetFilter');
    }

    public function test_cari_dan_filter_kategori_status(): void
    {
        $this->lagu(['judul' => 'Dangdut Malam', 'kategori' => 'Pop', 'status' => 'draf']);
        $h = $this->halaman();
        $h->set('q', 'dangdut')->assertSee('Dangdut Malam')->assertDontSee('Hari Bahagia');
        $h->set('q', '')->set('kat', 'Orkestra')->assertSee('Janji Setia')->assertDontSee('Hari Bahagia');
        $h->set('kat', 'semua')->call('atur', 'draf')->assertSee('Dangdut Malam')->assertDontSee('Janji Setia');
        $h->call('atur', 'bukan-status')->assertSet('fs', 'semua');
        $h->set('kat', 'Tidak Ada')->assertSet('kat', 'semua');
        $h->set('q', 'zzzz')->assertSee('Tidak ada lagu yang cocok');
    }

    public function test_pratinjau_putar(): void
    {
        $audio = $this->lagu(['judul' => 'Bisa Putar']);
        $yt = $this->lagu(['judul' => 'Dari Tube', 'sumber' => 'youtube', 'url' => null, 'youtube' => 'https://www.youtube.com/watch?v=abc']);
        $kosong = Lagu::where('kode', 'l1')->first();

        $h = $this->halaman()->call('putar', $audio->id)->assertSeeHtml('<audio controls autoplay preload="none" src="https://cdn.example.com/')->assertSee('Pemutar Bisa Putar');
        $h->call('putar', $yt->id)->assertSee('Buka di YouTube')->assertDontSeeHtml('<audio controls');
        $h->call('putar', $kosong->id)->assertSee('belum punya file audio');
        $h->call('putar', $kosong->id)->assertSet('putarId', null);
        $h->call('putar', 99999)->assertSet('putarId', null);
    }

    public function test_hapus_lagu_merapikan_urutan_dan_memperingatkan_pemakai(): void
    {
        $pemilik = User::factory()->create();
        Undangan::factory()->for($pemilik, 'pengguna')->create(['isi' => ['musLagu' => 'l3']]);
        Undangan::factory()->for($pemilik, 'pengguna')->create(['isi' => ['musLagu' => 'l3']]);
        $l3 = Lagu::where('kode', 'l3')->firstOrFail();

        $this->halaman()->call('tanyaHapus', $l3->id)->assertSet('dialogHapus', true)->assertSee('2 undangan sedang memilih lagu ini')->assertSee('lagu bawaan editor')->call('hapus');

        $this->assertDatabaseMissing('lagu', ['kode' => 'l3']);
        $this->assertSame([1, 2, 3, 4], Lagu::query()->urut()->pluck('urutan')->all());
    }

    public function test_hapus_audio_media_membuat_lagu_tanpa_sumber_bukan_error(): void
    {
        $audio = app(PenyimpanMedia::class)->simpan($this->mp3(), null);
        $l = $this->lagu(['sumber' => 'media', 'media_id' => $audio->id, 'url' => null]);
        $this->assertNotNull($l->urlAudio());
        app(PenyimpanMedia::class)->hapus($audio, paksa: true);
        $this->assertNull($l->fresh()->media_id);
        $this->assertNull($l->fresh()->urlAudio());
    }

    public function test_id_terkunci_dan_aksi_non_admin(): void
    {
        $l = $this->lagu();
        $h = $this->halaman()->call('bukaEdit', $l->id);
        try {
            $h->set('editId', Lagu::where('kode', 'l1')->value('id'));
            $this->fail('editId harus terkunci');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->halaman()->set('hapusId', $l->id);
    }

    public function test_aksi_tulis_non_admin_tidak_berjalan(): void
    {
        $biasa = User::factory()->create();
        Livewire::actingAs($biasa)->test(KatalogMusik::class)->assertForbidden();
        $this->assertSame('publik', Lagu::where('kode', 'l1')->value('status'));
    }

    // ------------------------------------------------------------------ editor, renderer, halaman publik

    public function test_opsi_editor_hanya_lagu_publik_sesuai_urutan(): void
    {
        $this->lagu(['judul' => 'Rahasia', 'status' => 'draf']);
        $this->lagu(['judul' => 'Terbuka', 'keterangan' => 'Piano', 'durasi' => 75]);
        $opsi = KatalogLagu::opsiEditor();
        $this->assertSame(['', 'Belum memilih lagu'], $opsi[0]);
        $this->assertSame('l1', $opsi[1][0]);
        $this->assertSame('Perayaan Cinta · Instrumental piano · 3:12', $opsi[1][1]);
        $labels = array_column($opsi, 1);
        $this->assertContains('Terbuka · Piano · 1:15', $labels);
        $this->assertNotContains('Rahasia', array_map(fn ($l) => explode(' · ', $l)[0], $labels));

        $meta = app(IsiUndangan::class)->meta('musLagu', []);
        $this->assertSame($opsi, $meta['opts']);
    }

    public function test_editor_menerima_lagu_katalog_dan_menolak_draf_atau_asing(): void
    {
        $baru = $this->lagu(['judul' => 'Pilihan Baru']);
        $draf = $this->lagu(['judul' => 'Draf', 'status' => 'draf']);
        $iso = app(IsiUndangan::class);
        $meta = $iso->meta('musLagu', []);

        $this->assertSame([$baru->kode, null], $iso->bersihkan($baru->kode, $meta));
        $this->assertSame(['l2', null], $iso->bersihkan('l2', $meta));
        $this->assertSame(['', null], $iso->bersihkan('', $meta));
        $this->assertNull($iso->bersihkan($draf->kode, $meta)[0]);
        $this->assertSame('', $iso->bersihkan('<script>', $meta)[0]); // tag dibuang; kosong = tanpa lagu
        $this->assertNull($iso->bersihkan('lagu-tidak-ada', $meta)[0]);
    }

    public function test_editor_memilih_lagu_dan_lagu_terhapus_tidak_diganti_diam_diam(): void
    {
        $pemilik = User::factory()->create();
        $baru = $this->lagu(['judul' => 'Pilihan Baru']);
        $u = Undangan::factory()->for($pemilik, 'pengguna')->create(['isi' => SkemaUndangan::isiAwal()]);

        $h = Livewire::actingAs($pemilik)->test(EditorUndangan::class, ['undangan' => $u->id]);
        $h->call('setBidang', 'musLagu', $baru->kode);
        $this->assertSame($baru->kode, $u->fresh()->isi['musLagu']);
        $h->call('setBidang', 'musLagu', 'tidak-ada');
        $this->assertSame($baru->kode, $u->fresh()->isi['musLagu']);

        $baru->delete();
        $h2 = Livewire::actingAs($pemilik)->test(EditorUndangan::class, ['undangan' => $u->id]);
        $h2->assertSet('isi.musLagu', '');
    }

    public function test_renderer_memakai_data_lagu_terpilih(): void
    {
        $audio = app(PenyimpanMedia::class)->simpan($this->mp3(), null);
        $lagu = $this->lagu(['judul' => 'Lagu Sistem', 'artis' => 'Penyanyi X', 'keterangan' => 'Piano solo', 'sumber' => 'media', 'media_id' => $audio->id, 'url' => null]);
        $bagian = ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];
        $html = fn (array $isi) => RenderUndangan::html(array_replace(SkemaUndangan::isiAwal(), $isi), $bagian, null);

        $h = $html(['musLagu' => $lagu->kode]);
        $this->assertStringContainsString('Lagu Sistem', $h);
        $this->assertStringContainsString('Piano solo', $h);
        $this->assertStringContainsString('data-sumber="'.e($audio->url()).'"', $h);
        $this->assertStringNotContainsString('data-youtube', $h);

        // judul yang diubah pengguna dipertahankan; link sendiri mengalahkan lagu katalog
        $h = $html(['musLagu' => $lagu->kode, 'musJudul' => 'Judul Saya', 'musUrl' => 'https://cdn.example.com/sendiri.mp3']);
        $this->assertStringContainsString('Judul Saya', $h);
        $this->assertStringNotContainsString('Lagu Sistem', $h);
        $this->assertStringContainsString('data-sumber="https://cdn.example.com/sendiri.mp3"', $h);

        // draf, dihapus, atau kode asing: tidak dipakai
        $lagu->update(['status' => 'draf']);
        $this->assertStringNotContainsString('data-sumber', $html(['musLagu' => $lagu->kode]));
        $this->assertStringNotContainsString('Lagu Sistem', $html(['musLagu' => $lagu->kode]));
        $this->assertStringNotContainsString('data-sumber', $html(['musLagu' => "x' onload='1"]));
    }

    public function test_renderer_lagu_youtube_dan_url_tidak_aman_diabaikan(): void
    {
        $bagian = ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];
        $yt = $this->lagu(['judul' => 'Tube', 'sumber' => 'youtube', 'url' => null, 'youtube' => 'https://youtu.be/abc123']);
        $h = RenderUndangan::html(array_replace(SkemaUndangan::isiAwal(), ['musLagu' => $yt->kode]), $bagian, null);
        $this->assertStringContainsString('data-youtube="https://youtu.be/abc123"', $h);
        $this->assertStringNotContainsString('data-sumber', $h);

        // data lagu rusak di basis data (url berskema berbahaya) tidak pernah jadi atribut
        $buruk = $this->lagu(['judul' => 'Buruk', 'url' => 'javascript:alert(1)']);
        $h = RenderUndangan::html(array_replace(SkemaUndangan::isiAwal(), ['musLagu' => $buruk->kode]), $bagian, null);
        $this->assertStringNotContainsString('javascript:', $h);
        $this->assertStringNotContainsString('data-sumber', $h);
    }

    public function test_halaman_publik_menyertakan_lagu_terpilih_dan_pemutar_mengenali_path_situs(): void
    {
        $audio = app(PenyimpanMedia::class)->simpan($this->mp3(), null);
        $lagu = $this->lagu(['judul' => 'Lagu Publik', 'sumber' => 'media', 'media_id' => $audio->id, 'url' => null]);
        $tema = Tema::factory()->create();
        $isi = array_replace(SkemaUndangan::isiAwal(), ['musLagu' => $lagu->kode]);
        $bagian = ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];
        Undangan::factory()->for(User::factory()->create(), 'pengguna')->dengan($tema)->create([
            'slug' => 'ada-musik', 'status' => Undangan::TERBIT, 'diterbitkan_at' => now(), 'isi' => $isi, 'bagian' => $bagian,
            'terbitan' => ['judul' => 'x', 'slug' => 'ada-musik', 'isi' => $isi, 'bagian' => $bagian, 'pengaturan' => [], 'diterbitkan_at' => now()->toIso8601String(),
                'tema' => $tema->only(['kode', 'nama', 'tier', 'warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks', 'font_judul'])],
        ]);

        $res = $this->withHeaders(['User-Agent' => self::UA])->get('/ada-musik')->assertOk();
        $res->assertSee('Lagu Publik')->assertSee('data-sumber="'.$audio->url().'"', false);
        $this->assertStringContainsString('media-src http: https: \'self\'', (string) $res->headers->get('Content-Security-Policy'));

        // tamu.js menerima path situs sebagai sumber audio
        $js = (string) file_get_contents(public_path('undangan/tamu.js'));
        $this->assertStringContainsString('amanAudio', $js);
        $this->assertStringContainsString('punyaAudio = amanAudio(sumber)', $js);
    }
}
