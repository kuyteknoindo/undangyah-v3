<?php

namespace Tests\Feature;

use App\Livewire\EditorUndangan;
use App\Models\Media;
use App\Models\Pengaturan;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use App\Services\IsiUndangan;
use App\Services\KonfigStorage;
use App\Services\PenerbitUndangan;
use App\Services\PenyimpanMedia;
use App\Support\SkemaUndangan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Concerns\BerkasUji;
use Tests\TestCase;

class EditorFotoTest extends TestCase
{
    use BerkasUji, RefreshDatabase;

    private User $user;

    private Tema $tema;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::DISK);
        $this->seed(PaketSeeder::class);
        $this->user = User::factory()->create();
        $this->tema = Tema::factory()->create(['nama' => 'Biru Klasik']);
    }

    private function buat(array $ubah = [], ?User $pemilik = null): Undangan
    {
        return Undangan::factory()->create(array_merge([
            'user_id' => ($pemilik ?? $this->user)->id,
            'tema_id' => $this->tema->id,
            'judul' => 'Raka & Sinta',
            'slug' => 'raka-sinta',
            'isi' => SkemaUndangan::isiAwal(),
            'bagian' => ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []],
        ], $ubah));
    }

    private function editor(Undangan $u, ?User $sebagai = null)
    {
        return Livewire::actingAs($sebagai ?? $this->user)->test(EditorUndangan::class, ['undangan' => $u->id]);
    }

    private function media(?User $pemilik = null, string $nama = 'foto.jpg'): Media
    {
        return app(PenyimpanMedia::class)->simpan($this->gambar($nama, 600, 400), $pemilik ?? $this->user, ['tipe' => ['gambar'], 'folder' => 'Foto']);
    }

    private function pasang($komponen, string $slot, Media $m)
    {
        return $komponen->call('bukaFoto', $slot)->call('pilihFoto', $m->id)->call('terapkanPilihan');
    }

    // ------------------------------------------------------------ pilih foto ke slot

    public function test_pilih_foto_ke_slot_menyimpan_id_dan_url_turunan_server_dan_tampil_di_kanvas(): void
    {
        $u = $this->buat();
        $m = $this->media();
        $k = $this->editor($u);
        $this->pasang($k, 'cover', $m);

        $k->assertSet('dialogFoto', false);
        $f = $u->fresh()->isi['_foto']['cover'];
        $this->assertSame($m->url(), $f['src']);
        $this->assertSame($m->id, $f['mid']);
        $this->assertArrayNotHasKey('crop', $f);
        $k->assertSeeHtml('src="'.$m->url().'"');
        $this->assertTrue($m->fresh()->dipakai);
    }

    public function test_pilih_bidang_foto_dari_kanvas_memilih_slot_dan_bagiannya(): void
    {
        $k = $this->editor($this->buat());
        $k->call('pilihBidang', 'foto:wanita')->assertSet('bagianAktif', 'mempelai')->assertSet('terpilih', 'foto:wanita');
        $k->call('pilihBidang', 'foto:g2')->assertSet('bagianAktif', 'gallery')->assertSet('terpilih', 'foto:g2');
        $k->call('pilihBidang', 'foto:cover')->assertSet('bagianAktif', 'sampul');
        // slot asing dan slot galeri yang tidak ada diabaikan
        $k->call('pilihBidang', 'foto:g999')->assertSet('terpilih', 'foto:cover');
        $k->call('pilihBidang', 'foto:retas')->assertSet('terpilih', 'foto:cover');
    }

    public function test_kartu_slot_tampil_di_panel_kanan_sesuai_bagian(): void
    {
        $k = $this->editor($this->buat());
        $k->call('pilihBagian', 'mempelai')->assertSee('Foto mempelai pria')->assertSee('Foto mempelai wanita')->assertSee('Pilih foto');
        $k->call('pilihBagian', 'penutup')->assertSee('Foto penutup');
        $k->call('pilihBagian', 'social')->assertSee('Foto saat dibagikan');
        $k->call('pilihBagian', 'gallery')->assertSee('Foto galeri')->assertSee('Tata letak');
    }

    public function test_media_orang_lain_aset_sistem_bukan_gambar_dan_id_ngawur_ditolak(): void
    {
        $u = $this->buat();
        $lain = $this->media(User::factory()->create(), 'milik-lain.jpg');
        $sistem = app(PenyimpanMedia::class)->simpan($this->gambar('sistem.jpg'), null, ['tipe' => ['gambar']]);
        $audio = app(PenyimpanMedia::class)->simpan($this->mp3(), $this->user);

        foreach ([$lain->id, $sistem->id, $audio->id, 999999] as $id) {
            $k = $this->editor($u)->call('bukaFoto', 'cover')->call('pilihFoto', $id);
            $k->assertSet('pilihanFoto', [])->assertSee('Foto tidak ditemukan di pustakamu.');
            $k->call('terapkanPilihan');
            $this->assertArrayNotHasKey('cover', $u->fresh()->isi['_foto'] ?? []);
        }
    }

    public function test_pustaka_hanya_menampilkan_foto_milik_sendiri_dan_bisa_dicari(): void
    {
        $this->media(null, 'pantai-senja.jpg');
        $this->media(null, 'gunung.jpg');
        $this->media(User::factory()->create(), 'rahasia-orang.jpg');
        $k = $this->editor($this->buat())->call('bukaFoto', 'cover');
        $k->assertSee('pantai-senja.jpg')->assertSee('gunung.jpg')->assertDontSee('rahasia-orang.jpg');
        $k->set('cariFoto', 'pantai')->assertSee('pantai-senja.jpg')->assertDontSee('gunung.jpg');
        $k->set('cariFoto', '100%_')->assertSee('Foto tidak ditemukan');
    }

    public function test_properti_sensitif_terkunci_dan_isi_foto_tidak_bisa_ditulis_klien(): void
    {
        $u = $this->buat();
        $k = $this->editor($u);
        foreach (['slotFoto' => 'cover', 'modeFoto' => 'galeri', 'pilihanFoto' => [1], 'slotCrop' => 'cover'] as $prop => $nilai) {
            try {
                $k->set($prop, $nilai);
                $this->fail("$prop seharusnya terkunci");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }
        }
        // jalur isian teks tidak menerima kunci "_foto" dari klien
        $k->call('setBidang', '_foto', 'https://jahat.example/x.jpg')->set('isi._foto', 'https://jahat.example/x.jpg');
        $k->call('setBidang', 'foto:cover', '/x.jpg');
        $this->assertStringNotContainsString('jahat.example', json_encode($u->fresh()->isi));
    }

    public function test_slot_tidak_sah_tidak_membuka_dialog(): void
    {
        $k = $this->editor($this->buat());
        foreach (['retas', 'g999', 'g', '../x', 'bgf', ''] as $slot) {
            $k->call('bukaFoto', $slot)->assertSet('dialogFoto', false);
        }
    }

    public function test_terapkan_tanpa_pilihan_memberi_galat(): void
    {
        $k = $this->editor($this->buat())->call('bukaFoto', 'cover')->call('terapkanPilihan');
        $k->assertSet('dialogFoto', true)->assertSee('Pilih satu foto dulu.');
    }

    public function test_ganti_foto_menghapus_crop_dan_foto_sama_mempertahankannya(): void
    {
        $u = $this->buat();
        $a = $this->media();
        $b = $this->media(null, 'b.jpg');
        $k = $this->editor($u);
        $this->pasang($k, 'pria', $a);
        $k->call('bukaCrop', 'pria')->call('simpanCrop', 'bingkai', 150, 30, 40);
        $this->assertSame(150, $u->fresh()->isi['_foto']['pria']['crop']['zoom']);

        $this->pasang($k, 'pria', $a);
        $this->assertSame(150, $u->fresh()->isi['_foto']['pria']['crop']['zoom']);

        $this->pasang($k, 'pria', $b);
        $this->assertArrayNotHasKey('crop', $u->fresh()->isi['_foto']['pria']);
        $this->assertSame($b->id, $u->fresh()->isi['_foto']['pria']['mid']);
    }

    public function test_hapus_foto_melepas_slot_dan_penanda_dipakai_diperbarui(): void
    {
        $u = $this->buat();
        $m = $this->media();
        $k = $this->editor($u);
        $this->pasang($k, 'penutup', $m);
        $this->assertTrue($m->fresh()->dipakai);

        $k->call('hapusFoto', 'penutup');
        $this->assertArrayNotHasKey('penutup', $u->fresh()->isi['_foto']);
        $this->assertFalse($m->fresh()->dipakai);
        $this->assertNotNull(Media::find($m->id), 'berkas pustaka tetap ada');
    }

    // ------------------------------------------------------------ unggah

    public function test_unggah_foto_baru_tersimpan_milik_pengguna_dan_otomatis_terpilih(): void
    {
        $k = $this->editor($this->buat())->call('bukaFoto', 'cover')
            ->set('unggahFoto', [UploadedFile::fake()->image('baru.png', 500, 500)]);

        $m = Media::where('user_id', $this->user->id)->first();
        $this->assertNotNull($m);
        $this->assertSame('Foto', $m->folder);
        $this->assertSame(Media::GAMBAR, $m->tipe);
        $k->assertSet('pilihanFoto', [$m->id])->assertSet('unggahFoto', []);
        $k->call('terapkanPilihan');
        $this->assertSame($m->url(), Undangan::where('user_id', $this->user->id)->first()->isi['_foto']['cover']['src'] ?? null);
    }

    public function test_unggah_berkas_bukan_gambar_ditolak_dengan_pesan(): void
    {
        $k = $this->editor($this->buat())->call('bukaFoto', 'cover')
            ->set('unggahFoto', [UploadedFile::fake()->createWithContent('jahat.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]);
        $this->assertSame(0, Media::count());
        $k->assertSet('pilihanFoto', [])->assertSee('jahat.svg');

        $k->set('unggahFoto', [$this->mp3()]);
        $this->assertSame(0, Media::count());
        $k->assertSee('hanya file gambar yang diterima di sini');
    }

    public function test_unggah_saat_kuota_penuh_menampilkan_galat_jelas(): void
    {
        Pengaturan::simpan('storage', ['kuota_mb' => 1, 'maks_mb' => 5, 'tipe' => KonfigStorage::BAWAAN['tipe']]);
        Media::create(['user_id' => $this->user->id, 'nama' => 'besar.jpg', 'path' => 'u1/besar.jpg', 'tipe' => 'gambar', 'mime' => 'image/jpeg', 'ukuran' => 1024 * 1024, 'folder' => 'Foto']);
        $k = $this->editor($this->buat())->call('bukaFoto', 'cover')->set('unggahFoto', [$this->gambar('lagi.jpg', 400, 400)]);
        $k->assertSee('kuota penyimpanan')->assertSee('Kuota penuh');
        $this->assertSame(1, Media::count());
    }

    public function test_unggah_dibatasi_sepuluh_foto_sekali_jalan(): void
    {
        $berkas = [];
        for ($i = 0; $i < 12; $i++) {
            $berkas[] = UploadedFile::fake()->image("f$i.jpg", 100, 100);
        }
        $k = $this->editor($this->buat())->call('bukaGaleri')->set('unggahFoto', $berkas);
        $this->assertSame(3, count($k->get('pilihanFoto')), 'terpilih dibatasi sisa jatah galeri paket gratis');
        $this->assertSame(10, Media::count());
        $k->assertSee('Maksimal 10 foto sekali unggah');
    }

    // ------------------------------------------------------------ crop

    public function test_crop_disimpan_sebagai_parameter_dan_tampil_di_kanvas_tanpa_mengubah_berkas(): void
    {
        $u = $this->buat();
        $m = $this->media();
        $ukuran = Storage::disk(Media::DISK)->size($m->path);
        $k = $this->editor($u);
        $this->pasang($k, 'cover', $m);
        $k->call('bukaCrop', 'cover')->assertSet('dialogCrop', true)->assertSee('Crop foto');
        $k->call('simpanCrop', 'asli', 200, 25, 75)->assertSet('dialogCrop', false);

        $this->assertSame(['ratio' => 'asli', 'zoom' => 200, 'x' => 25, 'y' => 75], $u->fresh()->isi['_foto']['cover']['crop']);
        $k->assertSeeHtml('object-fit:contain')->assertSeeHtml('object-position:25% 75%')->assertSeeHtml('scale(2)');
        $this->assertSame($ukuran, Storage::disk(Media::DISK)->size($m->path));
    }

    public function test_crop_dibatasi_rentang_dan_nilai_aneh_ditolak(): void
    {
        $u = $this->buat();
        $k = $this->editor($u);
        $this->pasang($k, 'cover', $this->media());
        $k->call('bukaCrop', 'cover')->call('simpanCrop', 'bingkai', 9999, -50, 500);
        $this->assertSame(['ratio' => 'bingkai', 'zoom' => 300, 'x' => 0, 'y' => 100], $u->fresh()->isi['_foto']['cover']['crop']);

        foreach ([['16:9', 100, 50, 50], ['bingkai', 'abc', 50, 50], ['bingkai', 100, null, 50], ['bingkai', [1], 50, 50], ['<script>', 100, 50, 50]] as $arg) {
            $k->call('bukaCrop', 'cover')->call('simpanCrop', ...$arg);
            $this->assertSame(300, $u->fresh()->isi['_foto']['cover']['crop']['zoom'], 'tidak berubah oleh nilai tak sah');
        }
    }

    public function test_crop_bawaan_menghapus_kunci_crop_dan_slot_kosong_atau_share_tidak_bisa_di_crop(): void
    {
        $u = $this->buat();
        $k = $this->editor($u);
        $k->call('bukaCrop', 'cover')->assertSet('dialogCrop', false);
        $this->pasang($k, 'cover', $this->media());
        $this->pasang($k, 'share', $this->media(null, 's.jpg'));
        $k->call('bukaCrop', 'share')->assertSet('dialogCrop', false);
        $k->call('bukaCrop', 'cover')->call('simpanCrop', 'bingkai', 120, 50, 50);
        $this->assertArrayHasKey('crop', $u->fresh()->isi['_foto']['cover']);
        $k->call('bukaCrop', 'cover')->call('simpanCrop', 'bingkai', 100, 50, 50);
        $this->assertArrayNotHasKey('crop', $u->fresh()->isi['_foto']['cover']);
    }

    // ------------------------------------------------------------ galeri

    public function test_tambah_foto_galeri_mengisi_slot_kosong_dulu_dan_dibatasi_paket(): void
    {
        $u = $this->buat();
        $a = $this->media(null, 'a.jpg');
        $b = $this->media(null, 'b.jpg');
        $c = $this->media(null, 'c.jpg');
        $d = $this->media(null, 'd.jpg');
        $k = $this->editor($u)->call('bukaGaleri');
        foreach ([$a, $b, $c] as $m) {
            $k->call('pilihFoto', $m->id);
        }
        $k->call('pilihFoto', $d->id)->assertSet('pilihanFoto', [$a->id, $b->id, $c->id])->assertSee('maksimal 3 foto galeri');
        $k->call('terapkanPilihan')->assertSet('dialogFoto', false);

        $isi = $u->fresh()->isi;
        $this->assertSame($a->url(), $isi['_foto']['g1']['src']);
        $this->assertSame($b->url(), $isi['_foto']['g2']['src']);
        $this->assertSame($c->url(), $isi['_foto']['g3']['src']);
        $this->assertSame(3, IsiUndangan::jumlahFotoGaleri($isi, $isi['_daftar']));

        $k->call('bukaGaleri')->assertSet('dialogFoto', false);
        $k->call('bukaFoto', 'g4')->call('pilihFoto', $d->id)->call('terapkanPilihan')->assertSee('Batas foto galeri paketmu');
        $this->assertArrayNotHasKey('g4', $u->fresh()->isi['_foto']);
    }

    public function test_paket_premium_boleh_lebih_banyak_dan_menambah_slot_baru_di_ujung(): void
    {
        $this->user->forceFill(['paket' => 'premium', 'paket_sampai' => now()->addMonth()])->save();
        $this->assertSame(20, app(PenyimpanMedia::class)->batasFotoGaleri($this->user->fresh()));
        $u = $this->buat();
        $semua = [];
        for ($i = 0; $i < 8; $i++) {
            $semua[] = $this->media(null, "p$i.jpg");
        }
        $k = $this->editor($u)->call('bukaGaleri');
        foreach ($semua as $m) {
            $k->call('pilihFoto', $m->id);
        }
        $k->call('terapkanPilihan');
        $isi = $u->fresh()->isi;
        $this->assertSame(8, IsiUndangan::jumlahFotoGaleri($isi, $isi['_daftar']));
        $this->assertCount(8, $isi['_daftar']['gl']);
        $this->assertGreaterThanOrEqual(100, max($isi['_daftar']['gl']));
        $this->assertSame($semua[7]->url(), $isi['_foto']['g'.$isi['_daftar']['gl'][7]]['src']);
    }

    public function test_foto_yang_sama_tidak_dobel_di_galeri(): void
    {
        $u = $this->buat();
        $m = $this->media();
        $k = $this->editor($u)->call('bukaGaleri')->call('pilihFoto', $m->id)->call('terapkanPilihan');
        $k->call('bukaGaleri')->call('pilihFoto', $m->id)->call('terapkanPilihan');
        $isi = $u->fresh()->isi;
        $this->assertSame(1, IsiUndangan::jumlahFotoGaleri($isi, $isi['_daftar']));
    }

    public function test_urut_hapus_dan_layout_galeri(): void
    {
        $u = $this->buat();
        $a = $this->media(null, 'a.jpg');
        $b = $this->media(null, 'b.jpg');
        $k = $this->editor($u)->call('bukaGaleri')->call('pilihFoto', $a->id)->call('pilihFoto', $b->id)->call('terapkanPilihan');

        $k->call('geserFotoGaleri', 1, 1);
        $this->assertSame([2, 1, 3, 4, 5, 6], $u->fresh()->isi['_daftar']['gl']);
        $k->call('geserFotoGaleri', 2, -1)->call('geserFotoGaleri', 2, -1);
        $this->assertSame([2, 1, 3, 4, 5, 6], $u->fresh()->isi['_daftar']['gl'], 'sudah di ujung');
        $k->call('geserFotoGaleri', 777, 1)->call('geserFotoGaleri', 1, 5);

        $k->call('hapusSlotGaleri', 2);
        $isi = $u->fresh()->isi;
        $this->assertSame([1, 3, 4, 5, 6], $isi['_daftar']['gl']);
        $this->assertArrayNotHasKey('g2', $isi['_foto']);
        $k->call('hapusFoto', 'g1');
        $this->assertSame([3, 4, 5, 6], $u->fresh()->isi['_daftar']['gl']);

        $k->call('aturLayoutGaleri', 'slider');
        $this->assertSame('slider', $u->fresh()->isi['_galeri']['layout']);
        $k->call('aturLayoutGaleri', 'carousel-jahat');
        $this->assertSame('slider', $u->fresh()->isi['_galeri']['layout']);
    }

    public function test_link_video_galeri_hanya_http_https(): void
    {
        $u = $this->buat();
        $k = $this->editor($u)->call('pilihBagian', 'gallery')->set('videoGaleri', 'javascript:alert(1)')->assertSee(IsiUndangan::PESAN_URL);
        $this->assertArrayNotHasKey('_galeri', $u->fresh()->isi);
        $k->set('videoGaleri', 'https://youtube.com/watch?v=abc')->assertSet('galatVideo', '');
        $this->assertSame('https://youtube.com/watch?v=abc', $u->fresh()->isi['_galeri']['video']);
    }

    // ------------------------------------------------------------ background fixed

    public function test_background_fixed_hanya_untuk_tema_yang_mendukung(): void
    {
        $u = $this->buat();
        $m = $this->media();
        $k = $this->editor($u)->call('bukaBgf')->assertSet('dialogBgf', false);
        $k->call('bukaFotoBgf')->call('pilihFoto', $m->id)->call('terapkanPilihan')->assertSee('Tema ini tidak memakai Background Fixed.');
        $this->assertArrayNotHasKey('_latarTetap', $u->fresh()->isi);
        $k->assertDontSee('Belum diatur')->assertDontSee('Gambar yang tetap diam di belakang seluruh bagian saat tamu menggulir undangan.</div>', false);
    }

    public function test_background_fixed_pilih_gambar_atur_dan_hapus(): void
    {
        $this->tema->forceFill(['latar_tetap' => ['global' => true, 'jenis' => 'image', 'section' => []]])->save();
        $u = $this->buat();
        $m = $this->media();
        $k = $this->editor($u)->assertSee('Background Fixed')->call('bukaBgf')->assertSet('dialogBgf', true);
        $k->call('bukaFotoBgf')->call('pilihFoto', $m->id)->call('terapkanPilihan')->assertSet('dialogFoto', false);
        $l = $u->fresh()->isi['_latarTetap'];
        $this->assertSame([true, $m->url(), $m->id], [$l['on'], $l['src'], $l['mid']]);
        $k->assertSeeHtml('uy-bgf');

        $k->call('simpanBgf', true, '#aabbcc', 500, 5, 99);
        $l = $u->fresh()->isi['_latarTetap'];
        $this->assertSame(['#AABBCC', 90, 20, 20], [$l['oc'], $l['oo'], $l['op'], $l['blur']]);
        $this->assertSame($m->url(), $l['src']);

        foreach ([[true, 'merah', 10, 50, 5], [true, '#fff', 10, 50, 5], ['ya', '#000000', 10, 50, 5], [true, '#000000', 'x', 50, 5], [true, '#000000', 10, 50, null]] as $arg) {
            $k->call('simpanBgf', ...$arg);
            $this->assertSame('#AABBCC', $u->fresh()->isi['_latarTetap']['oc']);
        }

        $k->call('simpanBgf', false, '#000000', 0, 100, 0);
        $this->assertFalse($u->fresh()->isi['_latarTetap']['on']);
        $k->call('hapusBgf');
        $this->assertArrayNotHasKey('_latarTetap', $u->fresh()->isi);
        $this->assertFalse($m->fresh()->dipakai);
    }

    public function test_background_fixed_aktif_tanpa_gambar_ditolak(): void
    {
        $this->tema->forceFill(['latar_tetap' => ['global' => true, 'jenis' => 'image', 'section' => []]])->save();
        $u = $this->buat();
        $this->editor($u)->call('simpanBgf', true, '#000000', 45, 100, 0);
        $this->assertArrayNotHasKey('_latarTetap', $u->fresh()->isi);
    }

    // ------------------------------------------------------------ kelengkapan

    public function test_kelengkapan_bagian_foto_dan_gallery_ikut_dinilai(): void
    {
        $iso = app(IsiUndangan::class);
        $isi = SkemaUndangan::isiAwal();
        $this->assertSame(['done' => false, 'any' => false], $iso->status('foto', $isi, $isi['_daftar']));
        $this->assertSame(['done' => false, 'any' => false], $iso->status('gallery', $isi, $isi['_daftar']));

        $isi['_foto'] = ['latar' => '/storage/media/u1/a.jpg', 'g2' => ['src' => '/storage/media/u1/b.jpg'], 'g99' => '/storage/media/u1/yatim.jpg'];
        $this->assertSame(['done' => true, 'any' => true], $iso->status('foto', $isi, $isi['_daftar']));
        $this->assertSame(['done' => true, 'any' => true], $iso->status('gallery', $isi, $isi['_daftar']));
        $this->assertSame(1, IsiUndangan::jumlahFotoGaleri($isi, $isi['_daftar']), 'slot yang tak ada di daftar tidak dihitung');
    }

    public function test_daftar_bagian_menampilkan_status_foto_bukan_menyusul_dan_saran_terbit(): void
    {
        $u = $this->buat();
        $k = $this->editor($u)->assertDontSee('Foto menyusul');
        $saran = collect(app(PenerbitUndangan::class)->peringatan($u->fresh()))->pluck('kunci')->all();
        $this->assertContains('sec:foto', $saran);
        $this->assertContains('sec:gallery', $saran);

        $this->pasang($k, 'latar', $this->media());
        $k->call('bukaGaleri')->call('pilihFoto', $this->media(null, 'g.jpg')->id)->call('terapkanPilihan');
        $saran = collect(app(PenerbitUndangan::class)->peringatan($u->fresh()))->pluck('kunci')->all();
        $this->assertNotContains('sec:foto', $saran);
        $this->assertNotContains('sec:gallery', $saran);
    }

    // ------------------------------------------------------------ simpan otomatis dan terbit

    public function test_simpan_otomatis_dan_aksi_lain_tidak_menghapus_kunci_foto(): void
    {
        $this->tema->forceFill(['latar_tetap' => ['global' => true, 'jenis' => 'image', 'section' => []]])->save();
        $u = $this->buat();
        $m = $this->media();
        $k = $this->editor($u);
        $this->pasang($k, 'cover', $m);
        $k->call('aturLayoutGaleri', 'masonry');
        $k->call('bukaFotoBgf')->call('pilihFoto', $m->id)->call('terapkanPilihan');

        $k->set('isi.priaP', 'Raka Baru')->call('toggleLingkaran')->call('tambahItem', 'ev')->call('pindahBagian', 'acara', 'quotes');
        $isi = $u->fresh()->isi;
        $this->assertSame($m->url(), $isi['_foto']['cover']['src']);
        $this->assertSame('masonry', $isi['_galeri']['layout']);
        $this->assertSame($m->url(), $isi['_latarTetap']['src']);
        $this->assertSame('Raka Baru', $isi['priaP']);
    }

    public function test_terbit_menyalin_beku_foto_dan_halaman_publik_menampilkannya(): void
    {
        $this->tema->forceFill(['latar_tetap' => ['global' => true, 'jenis' => 'image', 'section' => []]])->save();
        $u = $this->buat(['isi' => array_replace(SkemaUndangan::isiAwal(), ['priaP' => 'Raka', 'wanitaP' => 'Sinta', 'tglCover' => 'Sabtu, 14 Februari 2027'])]);
        $a = $this->media(null, 'sampul.jpg');
        $b = $this->media(null, 'galeri.jpg');
        $c = $this->media(null, 'latar.jpg');
        $k = $this->editor($u);
        $this->pasang($k, 'cover', $a);
        $k->call('bukaCrop', 'cover')->call('simpanCrop', 'bingkai', 180, 40, 60);
        $k->call('bukaGaleri')->call('pilihFoto', $b->id)->call('terapkanPilihan')->call('aturLayoutGaleri', 'slider');
        $k->call('bukaFotoBgf')->call('pilihFoto', $c->id)->call('terapkanPilihan');

        $k->call('bukaTerbit')->set('slugTerbit', 'raka-sinta-foto')->call('terbitkan');
        $u = $u->fresh();
        $this->assertTrue($u->sudahTerbit());
        $this->assertSame($a->url(), $u->terbitan['isi']['_foto']['cover']['src']);
        $this->assertSame(180, $u->terbitan['isi']['_foto']['cover']['crop']['zoom']);
        $this->assertSame('slider', $u->terbitan['isi']['_galeri']['layout']);
        $this->assertSame($c->url(), $u->terbitan['isi']['_latarTetap']['src']);

        // perubahan sesudah terbit tidak bocor ke halaman publik sebelum terbit ulang
        $k->call('hapusFoto', 'cover');
        $html = $this->get('/raka-sinta-foto')->assertOk()->getContent();
        $this->assertStringContainsString($a->url(), $html);
        $this->assertStringContainsString($b->url(), $html);
        $this->assertStringContainsString($c->url(), $html);
        $this->assertStringContainsString('object-position:40% 60%', $html);
        $this->assertStringContainsString('uy-galeri-slider', $html);
        $this->assertTrue($a->fresh()->dipakai, 'masih dirujuk terbitan');
    }

    // ------------------------------------------------------------ hak akses dan laju

    public function test_pengguna_lain_tidak_bisa_membuka_editor_dan_foto_orang_lain_tidak_bocor(): void
    {
        $u = $this->buat();
        $m = $this->media();
        $this->pasang($this->editor($u), 'cover', $m);
        $lain = User::factory()->create();
        Livewire::actingAs($lain)->test(EditorUndangan::class, ['undangan' => $u->id])->assertNotFound();
        $this->actingAs($lain)->get('/edit-undangan/'.$u->id)->assertNotFound();

        // undangan milik sendiri tidak bisa memakai foto milik orang lain walau id-nya ditebak
        $uLain = $this->buat(['slug' => 'lain'], $lain);
        $this->editor($uLain, $lain)->call('bukaFoto', 'cover')->call('pilihFoto', $m->id)->call('terapkanPilihan');
        $this->assertArrayNotHasKey('cover', $uLain->fresh()->isi['_foto'] ?? []);
    }

    public function test_aksi_foto_dibatasi_laju(): void
    {
        $u = $this->buat();
        $k = $this->editor($u);
        $this->pasang($k, 'cover', $this->media());
        for ($i = 0; $i < 130; $i++) {
            RateLimiter::hit('foto-editor:'.$this->user->id, 60);
        }
        $k->call('hapusFoto', 'cover');
        $this->assertArrayHasKey('cover', $u->fresh()->isi['_foto'], 'ditolak karena terlalu sering');
    }
}
