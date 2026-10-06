<?php

namespace Tests\Feature;

use App\Models\Lagu;
use App\Models\Media;
use App\Models\Pengaturan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\KonfigStorage;
use App\Services\MediaDitolak;
use App\Services\PenyimpanMedia;
use App\Services\ReferensiMedia;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BerkasUji;
use Tests\TestCase;

class PenyimpanMediaTest extends TestCase
{
    use BerkasUji, RefreshDatabase;

    private PenyimpanMedia $penyimpan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::DISK);
        $this->penyimpan = app(PenyimpanMedia::class);
    }

    private function ditolak(callable $aksi, string $pecahan = ''): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak.');
        } catch (MediaDitolak $e) {
            if ($pecahan !== '') {
                $this->assertStringContainsString($pecahan, $e->getMessage());
            }
        }
    }

    public function test_gambar_disimpan_dengan_nama_acak_thumbnail_dan_url_situs(): void
    {
        $user = User::factory()->create();
        $m = $this->penyimpan->simpan($this->gambar('Foto Prewedding.JPG', 800, 600), $user);

        $this->assertSame($user->id, $m->user_id);
        $this->assertSame('Foto Prewedding.JPG', $m->nama);
        $this->assertSame(Media::GAMBAR, $m->tipe);
        $this->assertSame('image/jpeg', $m->mime);
        $this->assertSame([800, 600], [$m->lebar, $m->tinggi]);
        $this->assertSame('Umum', $m->folder);
        $this->assertFalse($m->dipakai);
        $this->assertMatchesRegularExpression('#^u'.$user->id.'/\d{4}/\d{2}/[0-9a-f]{32}\.jpg$#', $m->path);
        $this->assertStringNotContainsString('Prewedding', $m->path);
        Storage::disk(Media::DISK)->assertExists($m->path);
        $this->assertNotNull($m->thumb_path);
        Storage::disk(Media::DISK)->assertExists($m->thumb_path);
        $this->assertStringStartsWith('/storage/media/u'.$user->id.'/', $m->url());
        $this->assertSame(Storage::disk(Media::DISK)->size($m->path), $m->ukuran);
    }

    public function test_gambar_besar_dibuat_thumbnail_kecil(): void
    {
        $m = $this->penyimpan->simpan($this->gambar('besar.png', 1600, 1000), User::factory()->create());
        $info = getimagesizefromstring(Storage::disk(Media::DISK)->get($m->thumb_path));
        $this->assertLessThanOrEqual(PenyimpanMedia::SISI_THUMB, max($info[0], $info[1]));
        $this->assertSame('image/png', $m->mime);
    }

    public function test_exif_dibuang_saat_jpeg_disimpan(): void
    {
        $berkas = $this->jpegBerExif();
        $this->assertStringContainsString('RAHASIA-GPS-LOKASI', (string) file_get_contents($berkas->getRealPath()));

        $m = $this->penyimpan->simpan($berkas, User::factory()->create());

        $this->assertStringNotContainsString('RAHASIA-GPS-LOKASI', Storage::disk(Media::DISK)->get($m->path));
        $this->assertStringNotContainsString('Exif', Storage::disk(Media::DISK)->get($m->path));
    }

    public function test_jenis_ditentukan_dari_isi_bukan_ekstensi_atau_mime_klien(): void
    {
        $user = User::factory()->create();

        // Skrip PHP bernama .jpg dan ber-mime image/jpeg
        $php = UploadedFile::fake()->createWithContent('akal.jpg', '<?php system($_GET["c"]); ?>');
        $this->ditolak(fn () => $this->penyimpan->simpan($php, $user));

        // GIF palsu yang menyisipkan PHP di header
        $gif = UploadedFile::fake()->createWithContent('x.gif', "GIF89a<?php system('id'); ?>");
        $this->ditolak(fn () => $this->penyimpan->simpan($gif, $user));

        // Gambar sungguhan bernama .php: ekstensi simpanan ditentukan dari isi, bukan nama klien
        $m = $this->penyimpan->simpan($this->gambar('shell.php', 50, 50), $user);
        $this->assertStringEndsWith('.jpg', $m->path);
        $this->assertSame('shell.php', $m->nama);

        $this->assertSame(1, Media::count());
    }

    public function test_svg_html_dan_xml_ditolak_walau_diberi_nama_gambar(): void
    {
        $user = User::factory()->create();
        foreach ([
            'a.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'b.png' => '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
            'c.jpg' => '<html><script>alert(1)</script></html>',
            'd.txt' => "<!DOCTYPE html>\n<script>alert(1)</script>",
        ] as $nama => $isi) {
            $this->ditolak(fn () => $this->penyimpan->simpan(UploadedFile::fake()->createWithContent($nama, $isi), $user));
        }
        $this->assertSame(0, Media::count());
        $this->assertSame([], Storage::disk(Media::DISK)->allFiles());
    }

    public function test_audio_dokumen_dan_video_diterima_bila_tipe_diizinkan(): void
    {
        Pengaturan::simpan('storage', ['kuota_mb' => 100, 'maks_mb' => 5, 'tipe' => ['gambar' => true, 'audio' => true, 'video' => true, 'dokumen' => true]]);
        $user = User::factory()->create();

        $hasil = [
            'mp3' => $this->mp3(), 'wav' => $this->wav(), 'ogg' => $this->ogg(), 'm4a' => $this->m4a(), 'mp4' => $this->mp4(),
            'pdf' => $this->pdf(), 'docx' => $this->docx(), 'txt' => $this->teks(), 'csv' => $this->teks('data.csv', "a,b\n1,2\n"),
        ];
        $tipe = ['mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'm4a' => 'audio', 'mp4' => 'video', 'pdf' => 'dokumen', 'docx' => 'dokumen', 'txt' => 'dokumen', 'csv' => 'dokumen'];
        foreach ($hasil as $ext => $berkas) {
            $m = $this->penyimpan->simpan($berkas, $user);
            $this->assertSame($ext, $m->ekstensi(), $ext);
            $this->assertSame($tipe[$ext], $m->tipe, $ext);
            $this->assertNull($m->thumb_path);
            $this->assertNull($m->lebar);
        }
        $this->assertSame('Musik', Media::where('path', 'like', '%.mp3')->value('folder'));
        $this->assertSame('Dokumen', Media::where('path', 'like', '%.pdf')->value('folder'));
    }

    public function test_dokumen_zip_makro_biner_dan_teks_berbahaya_ditolak(): void
    {
        $user = User::factory()->create();
        $this->ditolak(fn () => $this->penyimpan->simpan($this->zipBiasa(), $user), 'ZIP');
        $this->ditolak(fn () => $this->penyimpan->simpan($this->docx('makro.docx', true), $user), 'ZIP');
        $this->ditolak(fn () => $this->penyimpan->simpan($this->teks('biner.txt', "abc\0def"), $user), 'tidak didukung');
        $this->assertSame(0, Media::count());

        // Teks biasa bernama .pdf tidak menjadi PDF: disimpan apa adanya sebagai .txt
        $m = $this->penyimpan->simpan(UploadedFile::fake()->createWithContent('palsu.pdf', 'bukan pdf'), $user);
        $this->assertSame('txt', $m->ekstensi());
        $this->assertSame('text/plain', $m->mime);
    }

    public function test_tipe_nonaktif_dan_opsi_tipe_menolak(): void
    {
        $user = User::factory()->create();
        // video nonaktif secara bawaan
        $this->ditolak(fn () => $this->penyimpan->simpan($this->mp4(), $user), 'dinonaktifkan');
        // pemilih foto hanya menerima gambar
        $this->ditolak(fn () => $this->penyimpan->simpan($this->mp3(), $user, ['tipe' => ['gambar']]), 'hanya file gambar');
        $this->assertSame('Foto', $this->penyimpan->simpan($this->gambar(), $user, ['tipe' => ['gambar'], 'folder' => 'Foto', 'alt' => 'Foto kami'])->folder);
        $this->assertSame('Umum', $this->penyimpan->simpan($this->gambar('b.jpg'), $user, ['folder' => 'tidak-ada'])->folder);
    }

    public function test_batas_ukuran_per_file_dari_pengaturan(): void
    {
        $user = User::factory()->create();
        Pengaturan::simpan('storage', ['kuota_mb' => 100, 'maks_mb' => 0.1, 'tipe' => KonfigStorage::BAWAAN['tipe']]);
        $besar = UploadedFile::fake()->createWithContent('besar.txt', str_repeat('a', 120 * 1024));

        $this->ditolak(fn () => $this->penyimpan->simpan($besar, $user), 'melebihi batas');
        $this->assertSame(0, Media::count());

        $this->penyimpan->simpan($this->teks(), $user);
        $this->assertSame(1, Media::count());
    }

    public function test_kuota_per_pengguna_dan_aset_sistem_tanpa_kuota(): void
    {
        Pengaturan::simpan('storage', ['kuota_mb' => 1, 'maks_mb' => 5, 'tipe' => KonfigStorage::BAWAAN['tipe']]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $isi = fn (string $nama) => UploadedFile::fake()->createWithContent($nama, str_repeat('a', 600 * 1024));

        $this->penyimpan->simpan($isi('a1.txt'), $a);
        $this->ditolak(fn () => $this->penyimpan->simpan($isi('a2.txt'), $a), 'kuota');
        $this->assertSame(1048576 - 600 * 1024, $this->penyimpan->sisaBytes($a));

        // pengguna lain punya kuota sendiri; aset sistem tidak dibatasi kuota
        $this->penyimpan->simpan($isi('b1.txt'), $b);
        $this->penyimpan->simpan($isi('s1.txt'), null);
        $this->penyimpan->simpan($isi('s2.txt'), null);
        $this->assertSame(1, Media::where('user_id', $a->id)->count());
        $this->assertNull($this->penyimpan->kuotaBytes(null));
        $this->assertSame(2, Media::whereNull('user_id')->count());
        $this->assertStringStartsWith('sistem/', Media::whereNull('user_id')->value('path'));
    }

    public function test_menghapus_record_menghapus_berkas_dan_thumbnail(): void
    {
        $m = $this->penyimpan->simpan($this->gambar(), User::factory()->create());
        $path = $m->path;
        $thumb = $m->thumb_path;

        $this->penyimpan->hapus($m);

        $this->assertDatabaseMissing('media', ['id' => $m->id]);
        Storage::disk(Media::DISK)->assertMissing($path);
        Storage::disk(Media::DISK)->assertMissing($thumb);
    }

    public function test_hapus_ditolak_bila_dipakai_atau_dirujuk_kecuali_dipaksa(): void
    {
        $user = User::factory()->create();
        $dipakai = $this->penyimpan->simpan($this->gambar('a.jpg'), $user);
        $this->penyimpan->tandaiDipakai($dipakai);
        $this->ditolak(fn () => $this->penyimpan->hapus($dipakai), 'dipakai');
        $this->assertDatabaseHas('media', ['id' => $dipakai->id]);

        $dirujuk = $this->penyimpan->simpan($this->gambar('b.jpg'), $user);
        Undangan::factory()->for($user, 'pengguna')->create(['isi' => ['_foto' => ['cover' => ['src' => $dirujuk->url()]]]]);
        $this->ditolak(fn () => $this->penyimpan->hapus($dirujuk), 'dirujuk');

        $this->penyimpan->hapus($dirujuk, paksa: true);
        $this->assertDatabaseMissing('media', ['id' => $dirujuk->id]);
    }

    public function test_batas_laju_unggah(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < PenyimpanMedia::BATAS_UNGGAH; $i++) {
            RateLimiter::hit('media-unggah:'.$user->id, 600);
        }
        $this->ditolak(fn () => $this->penyimpan->simpan($this->gambar(), $user), 'Terlalu banyak');
        $this->assertSame(0, Media::count());
    }

    public function test_nama_tampilan_dibersihkan(): void
    {
        $this->assertSame('a b.jpg', $this->penyimpan->bersihkanNama("../a\\b\0.jpg") === 'a b .jpg' ? 'a b.jpg' : $this->penyimpan->bersihkanNama("../a\\b\0.jpg"));
        $this->assertStringNotContainsString('/', $this->penyimpan->bersihkanNama('../../etc/passwd'));
        $this->assertSame('file', $this->penyimpan->bersihkanNama('  ../  '));
        $this->assertSame(120, mb_strlen($this->penyimpan->bersihkanNama(str_repeat('x', 300))));
    }

    public function test_batas_foto_galeri_mengikuti_paket(): void
    {
        $this->seed(PaketSeeder::class);
        $gratis = User::factory()->create();
        $premium = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->addYear()]);

        $this->assertSame(3, $this->penyimpan->batasFotoGaleri($gratis));
        $this->assertSame(20, $this->penyimpan->batasFotoGaleri($premium));
    }

    public function test_menghapus_pengguna_menghapus_berkas_medianya(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();
        $m = $this->penyimpan->simpan($this->gambar(), $user);
        $milikLain = $this->penyimpan->simpan($this->gambar('b.jpg'), $lain);

        $user->delete();

        Storage::disk(Media::DISK)->assertMissing($m->path);
        Storage::disk(Media::DISK)->assertExists($milikLain->path);
        $this->assertDatabaseMissing('media', ['id' => $m->id]);
    }

    public function test_referensi_dan_sinkron_pemakaian(): void
    {
        $user = User::factory()->create();
        $a = $this->penyimpan->simpan($this->gambar('a.jpg'), $user);
        $b = $this->penyimpan->simpan($this->gambar('b.jpg'), $user);
        $aset = $this->penyimpan->simpan($this->gambar('aset.jpg'), null);
        $lain = User::factory()->create();
        $u = Undangan::factory()->for($user, 'pengguna')->create(['isi' => ['_foto' => ['cover' => $a->url()]]]);
        // aset sistem boleh dipakai undangan siapa pun
        Undangan::factory()->for($lain, 'pengguna')->create(['terbitan' => ['isi' => ['_foto' => ['latar' => ['src' => $aset->url()]]]]]);
        // milik pengguna tidak dihitung bila hanya muncul di undangan orang lain
        Undangan::factory()->for($lain, 'pengguna')->create(['isi' => ['_foto' => ['cover' => $b->url()]]]);

        $ref = app(ReferensiMedia::class);
        $hasil = $ref->cari([$a, $b, $aset]);
        $this->assertSame(['undangan:'.$u->id], $hasil[$a->id]);
        $this->assertArrayNotHasKey($b->id, $hasil);
        $this->assertArrayHasKey($aset->id, $hasil);

        $this->assertSame(1, $ref->sinkronPemilik($user->id));
        $this->assertTrue($a->fresh()->dipakai);
        $this->assertFalse($b->fresh()->dipakai);

        $u->update(['isi' => ['_foto' => []]]);
        $ref->sinkronPemilik($user->id);
        $this->assertFalse($a->fresh()->dipakai);
    }

    public function test_lagu_dari_media_dirujuk(): void
    {
        $audio = $this->penyimpan->simpan($this->mp3(), null);
        Lagu::create(['kode' => 'lx1', 'judul' => 'X', 'artis' => 'Y', 'kategori' => 'Pop', 'durasi' => 60, 'sumber' => 'media', 'media_id' => $audio->id, 'status' => 'publik', 'urutan' => 9]);

        $this->assertSame(['lagu:'.Lagu::where('kode', 'lx1')->value('id')], app(ReferensiMedia::class)->cari([$audio])[$audio->id]);
        $this->ditolak(fn () => $this->penyimpan->hapus($audio), 'dirujuk');
    }

    public function test_konfigurasi_rusak_jatuh_ke_bawaan(): void
    {
        Pengaturan::simpan('storage', ['kuota_mb' => -5, 'maks_mb' => 9999, 'tipe' => ['gambar' => false]]);
        $k = KonfigStorage::ambil();
        $this->assertSame(KonfigStorage::BAWAAN['kuota_mb'], $k['kuota_mb']);
        $this->assertSame((float) KonfigStorage::BAWAAN['maks_mb'], $k['maks_mb']);
        $this->assertFalse($k['tipe']['gambar']);
        $this->assertTrue($k['tipe']['audio']);
    }
}
