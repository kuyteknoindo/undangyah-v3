<?php

namespace Tests\Feature;

use App\Livewire\Admin\Penyimpanan;
use App\Models\Lagu;
use App\Models\LogAksiAdmin;
use App\Models\Media;
use App\Models\Pengaturan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\KonfigStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

class AdminStorageTest extends TestCase
{
    use KonfirmasiSandiTes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::DISK);
        Cache::flush();
    }

    private function halaman(?User $user = null)
    {
        return Livewire::actingAs($this->sudahKonfirmasiSandi($user ?? User::factory()->admin()->create()))->test(Penyimpanan::class);
    }

    private function buat(string $nama, ?User $pemilik = null, array $atribut = []): Media
    {
        $path = ($pemilik ? 'u'.$pemilik->id : 'sistem').'/2026/10/'.bin2hex(random_bytes(8)).'.jpg';
        Storage::disk(Media::DISK)->put($path, str_repeat('x', (int) ($atribut['ukuran'] ?? 1000)));

        return Media::create(array_merge([
            'user_id' => $pemilik?->id, 'nama' => $nama, 'path' => $path, 'tipe' => 'gambar', 'mime' => 'image/jpeg', 'ukuran' => 1000,
            'folder' => 'Umum', 'dipakai' => false, 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ], $atribut));
    }

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/storage')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/storage')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/storage')->assertOk()->assertSeeLivewire(Penyimpanan::class);
        Livewire::actingAs(User::factory()->create())->test(Penyimpanan::class)->assertForbidden();
    }

    public function test_ringkasan_pemakaian_nyata_per_tipe_dan_per_pengguna(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Penyimpan']);
        $this->buat('a.jpg', $budi, ['ukuran' => 3 * 1048576]);
        $this->buat('b.jpg', $budi, ['ukuran' => 1048576, 'dipakai' => true]);
        $this->buat('lagu.mp3', null, ['tipe' => 'audio', 'ukuran' => 2048]);

        $h = $this->halaman();
        $h->assertSee('4 MB')->assertSee('Budi Penyimpan')->assertSee('Aset sistem')->assertSee('Tanpa kuota');
        $h->assertSee('Gambar')->assertSee('2 file')->assertSee('1 file');
        // hanya 2 dari 3 file tidak dipakai
        $h->assertSeeHtml('<div class="kpi-v">2</div>');
        $this->assertSame(1, (int) Media::where('dipakai', true)->count());
    }

    public function test_ukuran_disk_nyata_dari_berkas_di_disk(): void
    {
        $this->buat('a.jpg', null, ['ukuran' => 5000]);
        $this->buat('b.jpg', null, ['ukuran' => 7000]);
        $this->halaman()->assertSee('11,7 KB');
    }

    public function test_peringatan_pengguna_melebihi_kuota(): void
    {
        Pengaturan::simpan('storage', ['kuota_mb' => 1, 'maks_mb' => 1, 'tipe' => KonfigStorage::BAWAAN['tipe']]);
        $this->buat('besar.jpg', User::factory()->create(), ['ukuran' => 2 * 1048576]);
        $this->halaman()->assertSee('1 pengguna memakai lebih dari kuota');
    }

    public function test_simpan_pengaturan_dibaca_layanan_dan_dicatat(): void
    {
        $admin = User::factory()->admin()->create();
        $this->halaman($admin)->assertSet('kuota', '100')->assertSet('maks', '10')
            ->set('kuota', '250')->set('maks', '2,5')->call('ubahTipe', 'video')->call('ubahTipe', 'dokumen')
            ->call('simpan')->assertHasNoErrors()->assertDispatched('toast');

        $k = KonfigStorage::ambil();
        $this->assertSame(250, $k['kuota_mb']);
        $this->assertSame(2.5, $k['maks_mb']);
        $this->assertSame(2621440, $k['maks_byte']);
        $this->assertSame(['gambar' => true, 'audio' => true, 'video' => true, 'dokumen' => false], $k['tipe']);
        $log = LogAksiAdmin::where('aksi', 'storage_atur')->firstOrFail();
        $this->assertSame($admin->id, $log->admin_id);
    }

    public function test_validasi_pengaturan(): void
    {
        $h = $this->halaman();
        $h->set('kuota', '0')->call('simpan')->assertHasErrors('kuota');
        $h->set('kuota', 'abc')->call('simpan')->assertHasErrors('kuota');
        $h->set('kuota', '-5')->call('simpan')->assertHasErrors('kuota');
        $h->set('kuota', '100')->set('maks', '13')->call('simpan')->assertHasErrors('maks');
        $h->set('maks', '0')->call('simpan')->assertHasErrors('maks');
        $h->set('maks', 'x')->call('simpan')->assertHasErrors('maks');
        $h->set('kuota', '5')->set('maks', '8')->call('simpan')->assertHasErrors(['maks']);
        $h->set('kuota', '100')->set('maks', '5');
        foreach (['gambar', 'audio', 'dokumen'] as $t) {
            $h->call('ubahTipe', $t);
        }
        $h->call('simpan')->assertHasErrors('tipe');
        $this->assertTrue(KonfigStorage::adaBawaan());
    }

    public function test_ubah_tipe_menolak_kunci_asing_dan_bawaan_batal(): void
    {
        $h = $this->halaman()->call('ubahTipe', 'php')->assertSet('tipe', KonfigStorage::BAWAAN['tipe']);
        $h->set('kuota', '9')->call('batalkanUbahan')->assertSet('kuota', '100');
        $h->set('kuota', '9')->call('ubahTipe', 'video')->call('kembalikanBawaan')->assertSet('kuota', '100')->assertSet('tipe', KonfigStorage::BAWAAN['tipe']);
    }

    public function test_aksi_ditolak_untuk_non_admin(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Penyimpanan::class)->assertForbidden();
        $this->assertTrue(KonfigStorage::adaBawaan());
    }

    public function test_bersihkan_hanya_file_tak_dipakai_tak_dirujuk_dan_cukup_lama(): void
    {
        $pemilik = User::factory()->create();
        $sampah = $this->buat('sampah.jpg', $pemilik);
        $sampahSistem = $this->buat('sampah-sistem.jpg');
        $dipakai = $this->buat('dipakai.jpg', $pemilik, ['dipakai' => true]);
        $dirujuk = $this->buat('dirujuk.jpg', $pemilik);
        $baru = $this->buat('baru.jpg', $pemilik, ['created_at' => now()->subHours(2)]);
        $lagu = $this->buat('lagu.mp3', null, ['tipe' => 'audio']);
        $asetDirujuk = $this->buat('aset-dirujuk.jpg');
        $terbitan = $this->buat('hanya-terbitan.jpg', $pemilik);

        Undangan::factory()->for($pemilik, 'pengguna')->create(['isi' => ['_galeri' => ['layout' => 'grid'], '_foto' => ['g1' => ['src' => $dirujuk->url()]]]]);
        Undangan::factory()->for($pemilik, 'pengguna')->create(['isi' => [], 'terbitan' => ['isi' => ['_foto' => ['cover' => $terbitan->url()]]]]);
        Undangan::factory()->for(User::factory()->create(), 'pengguna')->create(['isi' => ['_foto' => ['latar' => $asetDirujuk->url()]]]);
        Lagu::create(['kode' => 'lq', 'judul' => 'q', 'artis' => 'q', 'kategori' => 'Pop', 'durasi' => 10, 'sumber' => 'media', 'media_id' => $lagu->id, 'urutan' => 9]);

        $path = $sampah->path;
        $h = $this->halaman()->call('tanyaBersih')->assertSet('dialogBersih', true)->assertSee('Hapus 2 file')->assertSee('sampah.jpg')->call('bersihkan');
        $h->assertSet('dialogBersih', false)->assertDispatched('toast');

        $this->assertDatabaseMissing('media', ['id' => $sampah->id]);
        $this->assertDatabaseMissing('media', ['id' => $sampahSistem->id]);
        Storage::disk(Media::DISK)->assertMissing($path);
        foreach ([$dipakai, $dirujuk, $baru, $lagu, $asetDirujuk, $terbitan] as $aman) {
            $this->assertDatabaseHas('media', ['id' => $aman->id]);
            Storage::disk(Media::DISK)->assertExists($aman->path);
        }
        $this->assertSame(1, LogAksiAdmin::where('aksi', 'storage_bersih')->count());
    }

    public function test_bersihkan_tanpa_kandidat_memberi_info_dan_tidak_menghapus(): void
    {
        $this->buat('dipakai.jpg', null, ['dipakai' => true]);
        $this->halaman()->call('tanyaBersih')->assertSet('dialogBersih', false)->assertDispatched('toast')->call('bersihkan');
        $this->assertSame(1, Media::count());
        $this->assertSame(0, LogAksiAdmin::count());
    }

    public function test_bersihkan_dibatasi_laju(): void
    {
        $h = $this->halaman();
        for ($i = 0; $i < 6; $i++) {
            $this->buat("s{$i}.jpg");
            $h->call('bersihkan');
        }
        $this->assertSame(5, LogAksiAdmin::where('aksi', 'storage_bersih')->count());
        $this->assertSame(1, Media::count());
    }

    public function test_hitung_ulang_menyegarkan_ukuran_disk(): void
    {
        $h = $this->halaman()->assertSee('0 B');
        $this->buat('a.jpg', null, ['ukuran' => 2048]);
        $h->call('hitungUlang')->assertSee('2 KB');
    }
}
