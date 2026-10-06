<?php

namespace Tests\Feature;

use App\Livewire\Admin\PustakaMedia;
use App\Models\Lagu;
use App\Models\Media;
use App\Models\Pengaturan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\KonfigStorage;
use App\Services\PenyimpanMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Concerns\BerkasUji;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use BerkasUji, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::DISK);
        Storage::fake('local');
    }

    private function halaman(?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->admin()->create())->test(PustakaMedia::class);
    }

    private function buat(string $nama, ?User $pemilik = null, array $atribut = []): Media
    {
        $path = ($pemilik ? 'u'.$pemilik->id : 'sistem').'/2026/10/'.bin2hex(random_bytes(8)).'.jpg';
        Storage::disk(Media::DISK)->put($path, 'x');

        return Media::create(array_merge([
            'user_id' => $pemilik?->id, 'nama' => $nama, 'path' => $path, 'tipe' => 'gambar', 'mime' => 'image/jpeg', 'ukuran' => 1000,
            'lebar' => 10, 'tinggi' => 10, 'folder' => 'Umum', 'dipakai' => false,
        ], $atribut));
    }

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/media')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/media')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/media')->assertOk()->assertSeeLivewire(PustakaMedia::class);
        Livewire::actingAs(User::factory()->create())->test(PustakaMedia::class)->assertForbidden();
    }

    public function test_keadaan_kosong_dan_kpi(): void
    {
        $this->halaman()->assertSee('Belum ada media')->assertSee('Total file');

        $this->buat('a.jpg', null, ['ukuran' => 2048]);
        $this->buat('b.mp3', null, ['tipe' => 'audio', 'ukuran' => 1024]);
        $this->halaman()->assertSee('a.jpg')->assertSee('b.mp3')->assertSee('3 KB')->assertDontSee('Belum ada media');
    }

    public function test_unggah_banyak_file_menjadi_aset_sistem(): void
    {
        $admin = User::factory()->admin()->create();
        $this->halaman($admin)
            ->set('berkas', [$this->gambar('satu.jpg'), $this->mp3('dua.mp3'), $this->pdf('tiga.pdf')])
            ->assertSet('galatUnggah', [])
            ->assertSet('berkas', [])
            ->assertDispatched('toast');

        $this->assertSame(3, Media::whereNull('user_id')->count());
        $this->assertSame(['Umum', 'Musik', 'Dokumen'], Media::orderBy('id')->pluck('folder')->all());
        foreach (Media::all() as $m) {
            Storage::disk(Media::DISK)->assertExists($m->path);
        }
    }

    public function test_unggah_menolak_file_buruk_tetapi_menyimpan_yang_baik(): void
    {
        Pengaturan::simpan('storage', ['kuota_mb' => 100, 'maks_mb' => 0.1, 'tipe' => KonfigStorage::BAWAAN['tipe']]);
        $svg = UploadedFile::fake()->createWithContent('jahat.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $besar = UploadedFile::fake()->createWithContent('besar.txt', str_repeat('a', 200 * 1024));
        $video = $this->mp4();

        $h = $this->halaman()->set('berkas', [$this->teks('ok.txt'), $svg, $besar, $video]);

        $this->assertCount(3, $h->get('galatUnggah'));
        $h->assertSee('3 file tidak diunggah')->assertSee('jahat.svg')->assertSee('melebihi batas')->assertSee('dinonaktifkan');
        $this->assertSame(['ok.txt'], Media::pluck('nama')->all());
    }

    public function test_unggah_oleh_non_admin_ditolak(): void
    {
        Livewire::actingAs(User::factory()->create())->test(PustakaMedia::class)->assertForbidden();
        $this->assertSame(0, Media::count());
    }

    public function test_cari_filter_tipe_folder_pemilik_dan_urut(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso']);
        $this->buat('prewedding.jpg', null, ['alt' => 'Taman bunga', 'folder' => 'Foto', 'ukuran' => 500, 'created_at' => now()->subDays(3)]);
        $this->buat('lagu-akad.mp3', null, ['tipe' => 'audio', 'folder' => 'Musik', 'ukuran' => 9000, 'created_at' => now()->subDays(2)]);
        $this->buat('milik-budi.png', $budi, ['ukuran' => 100, 'created_at' => now()->subDay()]);

        $h = $this->halaman();
        $h->set('q', 'taman')->assertSee('prewedding.jpg')->assertDontSee('lagu-akad.mp3');
        $h->set('q', '')->call('atur', 'audio')->assertSee('lagu-akad.mp3')->assertDontSee('prewedding.jpg');
        $h->call('atur', 'semua')->set('folder', 'Foto')->assertSee('prewedding.jpg')->assertDontSee('milik-budi.png');
        $h->set('folder', 'semua')->set('pemilik', 'pengguna')->assertSee('milik-budi.png')->assertDontSee('prewedding.jpg')
            ->call('aturTampilan', 'daftar')->assertSee('Budi Santoso')->call('aturTampilan', 'grid');
        $h->set('pemilik', 'sistem')->assertDontSee('milik-budi.png');
        $h->set('pemilik', 'semua')->set('urut', 'ukuran')->assertSeeInOrder(['lagu-akad.mp3', 'prewedding.jpg', 'milik-budi.png']);
        $h->set('urut', 'nama')->assertSeeInOrder(['lagu-akad.mp3', 'milik-budi.png', 'prewedding.jpg']);
        $h->set('urut', 'baru')->assertSeeInOrder(['milik-budi.png', 'lagu-akad.mp3', 'prewedding.jpg']);
        // nilai filter di luar daftar kembali ke bawaan
        $h->set('folder', 'Rahasia')->assertSet('folder', 'semua')->set('urut', 'acak')->assertSet('urut', 'baru');
        $h->set('q', 'tidak-ada-file-ini')->assertSee('Tidak ada file yang cocok')->call('resetFilter')->assertSet('q', '')->assertSee('prewedding.jpg');
    }

    public function test_pencarian_menganggap_persen_dan_garis_bawah_sebagai_teks_biasa(): void
    {
        $this->buat('a_b.jpg');
        $this->buat('axb.jpg');
        $this->halaman()->set('q', 'a_b')->assertSee('a_b.jpg')->assertDontSee('axb.jpg')->set('q', '%')->assertSee('Tidak ada file yang cocok');
    }

    public function test_tampilan_daftar_dan_halaman(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->buat(sprintf('foto-%02d.jpg', $i), null, ['created_at' => now()->subMinutes($i)]);
        }
        $h = $this->halaman()->assertSee('Menampilkan 24 dari 30 file')->call('berikutnya')->assertSee('Menampilkan 6 dari 30 file')->assertSee('2 / 2');
        $h->call('aturTampilan', 'daftar')->assertSee('Menampilkan 20 dari 30 file')->assertSeeHtml('<table class="tabel">');
    }

    public function test_detail_ubah_nama_alt_folder_dan_penanda(): void
    {
        $m = $this->buat('lama.jpg');
        $h = $this->halaman()->call('bukaDetail', $m->id)->assertSet('dNama', 'lama.jpg')->assertSet('dialogDetail', true);
        $h->set('dNama', ' Baru.jpg ')->set('dAlt', '<b>Foto</b> taman')->set('dFolder', 'Ornamen')->set('dDipakai', true)->call('simpanDetail')
            ->assertHasNoErrors()->assertSet('dialogDetail', false)->assertDispatched('toast');

        $m->refresh();
        $this->assertSame('Baru.jpg', $m->nama);
        $this->assertSame('Foto taman', $m->alt);
        $this->assertSame('Ornamen', $m->folder);
        $this->assertTrue($m->dipakai);
    }

    public function test_detail_validasi(): void
    {
        $m = $this->buat('x.jpg');
        $h = $this->halaman()->call('bukaDetail', $m->id);
        $h->set('dNama', '')->call('simpanDetail')->assertHasErrors(['dNama' => 'required']);
        $h->set('dNama', 'a/b.jpg')->call('simpanDetail')->assertHasErrors(['dNama' => 'regex']);
        $h->set('dNama', 'a|b.jpg')->call('simpanDetail')->assertHasErrors(['dNama' => 'regex']);
        $h->set('dNama', str_repeat('x', 121))->call('simpanDetail')->assertHasErrors(['dNama' => 'max']);
        $h->set('dNama', 'ok.jpg')->set('dFolder', 'Rahasia')->call('simpanDetail')->assertHasErrors(['dFolder' => 'in']);
        $h->set('dFolder', 'Umum')->set('dAlt', str_repeat('a', 301))->call('simpanDetail')->assertHasErrors(['dAlt' => 'max']);
        $this->assertSame('x.jpg', $m->fresh()->nama);
    }

    public function test_detail_media_tidak_ada_404_dan_id_terkunci(): void
    {
        $this->halaman()->call('bukaDetail', 9999)->assertNotFound();

        $m = $this->buat('x.jpg');
        $h2 = $this->halaman()->call('bukaDetail', $m->id);
        $lain = $this->buat('lain.jpg');
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $h2->set('detailId', $lain->id);
    }

    public function test_hapus_ids_terkunci(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->halaman()->set('hapusIds', [1, 2]);
    }

    public function test_hapus_satu_file_menghapus_berkas(): void
    {
        $m = $this->buat('hapus-aku.jpg');
        $path = $m->path;
        $h = $this->halaman()->call('tanyaHapus', [$m->id])->assertSet('dialogHapus', true)->assertSee('Hapus file ini?')->call('hapus')->assertSet('dialogHapus', false);

        $this->assertDatabaseMissing('media', ['id' => $m->id]);
        Storage::disk(Media::DISK)->assertMissing($path);
        $h->assertDispatched('toast');
    }

    public function test_hapus_file_dipakai_dilewati_kecuali_dipaksa(): void
    {
        $pakai = $this->buat('dipakai.jpg', null, ['dipakai' => true]);
        $bebas = $this->buat('bebas.jpg');
        $dirujuk = $this->buat('dirujuk.jpg');
        Lagu::create(['kode' => 'lz', 'judul' => 'z', 'artis' => 'z', 'kategori' => 'Pop', 'durasi' => 10, 'sumber' => 'media', 'media_id' => $dirujuk->id, 'urutan' => 9]);

        $h = $this->halaman()->call('tanyaHapus', [$pakai->id, $bebas->id, $dirujuk->id])->assertSee('Hapus juga file yang sedang dipakai')->call('hapus');
        $this->assertDatabaseMissing('media', ['id' => $bebas->id]);
        $this->assertDatabaseHas('media', ['id' => $pakai->id]);
        $this->assertDatabaseHas('media', ['id' => $dirujuk->id]);

        $this->halaman()->call('tanyaHapus', [$pakai->id, $dirujuk->id])->set('hapusPaksa', true)->call('hapus');
        $this->assertDatabaseMissing('media', ['id' => $pakai->id]);
        $this->assertDatabaseMissing('media', ['id' => $dirujuk->id]);
    }

    public function test_hapus_menolak_referensi_undangan(): void
    {
        $pemilik = User::factory()->create();
        $m = $this->buat('cover.jpg', $pemilik);
        $u = Undangan::factory()->for($pemilik, 'pengguna')->create(['isi' => ['_foto' => ['cover' => ['src' => $m->url()]]]]);

        $this->halaman()->call('tanyaHapus', [$m->id])->assertSee('Hapus juga file yang sedang dipakai')->call('hapus');
        $this->assertDatabaseHas('media', ['id' => $m->id]);

        // detail menampilkan rujukan
        $this->halaman()->call('bukaDetail', $m->id)->assertSee('1 undangan');
        $this->assertNotNull($u);
    }

    public function test_pilih_banyak_lalu_hapus_terpilih(): void
    {
        $a = $this->buat('a.jpg');
        $b = $this->buat('b.jpg');
        $c = $this->buat('c.jpg');
        $h = $this->halaman()->call('pilih', $a->id)->call('pilih', $b->id)->call('pilih', 99999)
            ->assertSet('dipilih', [$a->id, $b->id])->assertSee('2 file dipilih');
        $h->call('pilih', $b->id)->assertSet('dipilih', [$a->id]);
        $h->call('pilihSemuaTampil')->assertSet('dipilih', fn ($v) => count($v) === 3);
        $h->call('pilihSemuaTampil')->assertSet('dipilih', []);
        $h->call('pilih', $a->id)->call('pilih', $c->id)->call('tanyaHapus')->call('hapus')->assertSet('dipilih', []);

        $this->assertSame([$b->id], Media::pluck('id')->all());
    }

    public function test_tanya_hapus_mengabaikan_id_yang_tidak_ada(): void
    {
        $this->halaman()->call('tanyaHapus', [123456])->assertSet('dialogHapus', false);
    }

    public function test_aksi_tulis_dibatasi_laju(): void
    {
        $m = $this->buat('x.jpg');
        $h = $this->halaman()->call('bukaDetail', $m->id);
        for ($i = 0; $i < 61; $i++) {
            $h->call('simpanDetail');
        }
        $h->assertDispatched('toast');
        $this->assertSame('x.jpg', $m->fresh()->nama);
    }

    public function test_batas_unggah_ditampilkan_dari_pengaturan(): void
    {
        Pengaturan::simpan('storage', ['kuota_mb' => 50, 'maks_mb' => 2.5, 'tipe' => KonfigStorage::BAWAAN['tipe']]);
        $this->halaman()->assertSee('Maks 2,5 MB per file');
        $this->assertSame('1,5 MB', PenyimpanMedia::teksUkuran(1572864));
    }
}
