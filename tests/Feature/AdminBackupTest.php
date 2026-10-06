<?php

namespace Tests\Feature;

use App\Jobs\JalankanBackup;
use App\Livewire\Admin\BackupRestore;
use App\Models\Backup;
use App\Models\LogAksiAdmin;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\PembuatBackup;
use App\Services\PengaturanBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\PembuatBackupUji;
use Tests\Concerns\SiapkanBackupUji;
use Tests\TestCase;

class AdminBackupTest extends TestCase
{
    use RefreshDatabase;
    use SiapkanBackupUji;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanBackup();
    }

    protected function tearDown(): void
    {
        $this->bersihkanBackup();
        parent::tearDown();
    }

    private function halaman(?User $admin = null)
    {
        return Livewire::actingAs($admin ?? User::factory()->admin()->create())->test(BackupRestore::class);
    }

    private function backupNyata(?User $oleh = null): Backup
    {
        return app(PembuatBackup::class)->buat('database', $oleh);
    }

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/backup')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/backup')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/backup')->assertOk()->assertSeeLivewire(BackupRestore::class)->assertSee('Backup sekarang');
        Livewire::actingAs(User::factory()->create())->test(BackupRestore::class)->assertForbidden();
    }

    public function test_aksi_ditolak_bila_peran_admin_dicabut_setelah_halaman_dibuka(): void
    {
        $admin = User::factory()->admin()->create();
        $b = $this->backupNyata();
        foreach ([['buat'], ['verifikasi', $b->id], ['tanyaHapus', $b->id], ['hapus'], ['simpanPengaturan'], ['tanyaUji', $b->id], ['ujiPulih'], ['terapkanRetensi'], ['tanyaRetensi']] as $panggilan) {
            $admin->forceFill(['role' => User::PERAN_ADMIN])->save();
            $k = Livewire::actingAs($admin)->test(BackupRestore::class);
            $admin->forceFill(['role' => User::PERAN_USER])->save();
            $k->call(...$panggilan)->assertForbidden();
        }
        $this->assertModelExists($b);
        $this->assertFileExists($this->dirBackup.'/'.$b->berkas);
    }

    public function test_halaman_menampilkan_riwayat_ringkasan_dan_instruksi_restore_manual_tanpa_sandi(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Uji']);
        $b = $this->backupNyata($admin);
        Backup::factory()->gagal('pg_dump gagal (kode 1): koneksi ditolak')->create(['nama' => 'backup-lama']);

        $this->halaman($admin)
            ->assertSee($b->nama)
            ->assertSee(substr($b->checksum, 0, 16))
            ->assertSee('Admin Uji')
            ->assertSee('koneksi ditolak')
            ->assertSee('Restore ke database hidup sengaja tidak otomatis')
            ->assertSee('pg_restore --no-owner --no-acl')
            ->assertSee('&lt;NAMA_DB_BARU&gt;', false)
            ->assertDontSee(self::SANDI_UJI);
    }

    public function test_buat_backup_database_mencatat_log_dan_mengaktifkan_unduhan(): void
    {
        $admin = User::factory()->admin()->create();
        $this->halaman($admin)->set('label', 'sebelum-update')->call('buat')->assertHasNoErrors()->assertDispatched('toast', tipe: 'sukses');

        $b = Backup::firstOrFail();
        $this->assertSame(['sebelum-update', Backup::SUKSES, $admin->id], [$b->nama, $b->status, $b->dibuat_oleh]);
        $this->assertFileExists($this->dirBackup.'/'.$b->berkas);
        $log = LogAksiAdmin::where('aksi', 'buat_backup')->firstOrFail();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame($b->id, $log->rincian['backup_id']);
    }

    public function test_buat_cepat_dan_pilihan_tipe(): void
    {
        $k = $this->halaman()->set('tipe', 'file')->set('label', 'abaikan')->call('buatCepat');
        $this->assertSame('database', Backup::firstOrFail()->tipe);
        $k->assertSet('tipe', 'database')->assertSet('label', '');

        $this->halaman()->call('pilihTipe', 'penuh')->assertSet('tipe', 'penuh')->call('pilihTipe', 'aneh')->assertSet('tipe', 'database');
    }

    public function test_validasi_nama_dan_tipe(): void
    {
        $k = $this->halaman();
        $k->set('label', '../../etc/passwd')->call('buat')->assertHasErrors('label');
        $k->set('label', str_repeat('a', 61))->call('buat')->assertHasErrors('label');
        $k->set('label', 'ok')->set('tipe', 'hapus-semua')->call('buat')->assertHasErrors('tipe');
        $this->assertSame(0, Backup::count());
        $this->assertEmpty($this->perintahJalan('pg_dump'));
    }

    public function test_batas_tiga_backup_per_jam_per_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $k = $this->halaman($admin);
        foreach (range(1, 3) as $_) {
            $k->call('buatCepat')->assertDispatched('toast', tipe: 'sukses');
        }
        $k->call('buatCepat')->assertDispatched('toast', fn ($nama, $p) => $p['tipe'] === 'galat' && str_contains($p['pesan'], 'Batas 3 backup per jam'));
        $this->assertSame(3, Backup::count());

        $lain = $this->halaman(User::factory()->admin()->create());
        $lain->call('buatCepat');
        $this->assertSame(4, Backup::count(), 'admin lain punya jatah sendiri');
    }

    public function test_ruang_disk_kurang_menolak_dan_tidak_memakai_jatah(): void
    {
        PembuatBackupUji::$bebas = 1_000_000;
        $admin = User::factory()->admin()->create();
        $this->halaman($admin)->call('buatCepat')->assertDispatched('toast', fn ($n, $p) => $p['tipe'] === 'galat' && str_contains($p['pesan'], 'Ruang disk tidak cukup'));

        $this->assertSame(0, Backup::count());
        $this->assertSame(0, RateLimiter::attempts('backup-buat:'.$admin->id));
    }

    public function test_backup_gagal_ditampilkan_sebagai_galat(): void
    {
        $this->proses['pg_dump_gagal'] = 'FATAL: password authentication failed '.self::SANDI_UJI;
        $this->halaman()->call('buatCepat')->assertDispatched('toast', fn ($n, $p) => $p['tipe'] === 'galat' && str_contains($p['pesan'], 'Backup gagal') && ! str_contains($p['pesan'], self::SANDI_UJI))
            ->assertDontSee(self::SANDI_UJI);
        $this->assertSame(Backup::GAGAL, Backup::firstOrFail()->status);
    }

    public function test_mode_antrean_hanya_memasukkan_job(): void
    {
        Bus::fake();
        config(['backup.antrean' => true]);
        $this->halaman()->call('buatCepat')->assertDispatched('toast', tipe: 'info');

        $b = Backup::firstOrFail();
        $this->assertSame(Backup::BERJALAN, $b->status);
        Bus::assertDispatched(JalankanBackup::class, fn ($j) => $j->backupId === $b->id);
        $this->assertEmpty($this->perintahJalan('pg_dump'));
    }

    public function test_job_mengerjakan_backup_berjalan(): void
    {
        $b = app(PembuatBackup::class)->mulai('database');
        (new JalankanBackup($b->id))->handle(app(PembuatBackup::class));

        $this->assertSame(Backup::SUKSES, $b->refresh()->status);
    }

    public function test_verifikasi_dari_halaman_lolos_dan_gagal_bila_berkas_rusak(): void
    {
        $admin = User::factory()->admin()->create();
        $b = $this->backupNyata();
        $k = $this->halaman($admin)->call('verifikasi', $b->id)->assertDispatched('toast', tipe: 'sukses')->assertSee('Terverifikasi');
        $this->assertTrue($b->refresh()->verifikasi['ok']);
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'verifikasi_backup']);

        file_put_contents($this->dirBackup.'/'.$b->berkas, 'rusak');
        $k->call('verifikasi', $b->id)->assertDispatched('toast', fn ($n, $p) => $p['tipe'] === 'galat' && str_contains($p['pesan'], 'Checksum tidak cocok'));
        $this->assertFalse($b->refresh()->verifikasi['ok']);

        $k->call('verifikasi', 999999)->assertNotFound();
    }

    public function test_hapus_butuh_dialog_konfirmasi_lalu_menghapus_berkas_dan_mencatat(): void
    {
        $b = $this->backupNyata();
        $jalur = $this->dirBackup.'/'.$b->berkas;

        $k = $this->halaman()->call('tanyaHapus', $b->id)->assertSet('dialogHapus', true)->assertSet('hapusId', $b->id);
        $this->assertFileExists($jalur, 'belum konfirmasi: berkas masih ada');
        $k->call('hapus')->assertSet('dialogHapus', false);

        $this->assertFileDoesNotExist($jalur);
        $this->assertModelMissing($b);
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'hapus_backup']);
    }

    public function test_id_hapus_dan_uji_terkunci_dari_manipulasi_klien(): void
    {
        $b = $this->backupNyata();
        $k = $this->halaman();

        try {
            $k->set('hapusId', $b->id);
            $this->fail('hapusId harus #[Locked].');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $k->set('ujiId', $b->id);
    }

    public function test_hapus_tanpa_memilih_dulu_tidak_berefek_dan_backup_berjalan_tidak_bisa_dihapus(): void
    {
        $b = $this->backupNyata();
        $jalan = Backup::factory()->create(['status' => 'berjalan', 'berkas' => null]);
        $k = $this->halaman();

        $k->call('hapus');
        $this->assertModelExists($b);
        $k->call('tanyaHapus', $jalan->id)->assertSet('dialogHapus', false)->assertDispatched('toast', tipe: 'galat');
        $this->assertModelExists($jalan);
    }

    public function test_simpan_pengaturan_otomatis_valid_dan_tercatat(): void
    {
        $admin = User::factory()->admin()->create();
        $this->halaman($admin)->set('otomatis', true)->set('frekuensi', 'mingguan')->set('retensiHari', '14')->set('simpanMinimal', '10')->set('tipeOtomatis', 'penuh')
            ->call('simpanPengaturan')->assertHasNoErrors()->assertDispatched('toast', tipe: 'sukses');

        $this->assertSame(['otomatis' => true, 'frekuensi' => 'mingguan', 'retensi_hari' => 14, 'simpan_minimal' => 10, 'tipe' => 'penuh'], PengaturanBackup::ambil());
        $this->assertSame($admin->id, Pengaturan::ambil('backup.diubah_oleh'));
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'atur_backup', 'admin_id' => $admin->id]);
    }

    public function test_pengaturan_default_nonaktif_dan_nilai_tidak_valid_ditolak(): void
    {
        $k = $this->halaman()->assertSet('otomatis', false)->assertSet('frekuensi', 'harian')->assertSet('retensiHari', '30');
        $k->set('frekuensi', 'tiap-detik')->call('simpanPengaturan')->assertHasErrors('frekuensi');
        $k->set('frekuensi', 'harian')->set('retensiHari', '9999')->call('simpanPengaturan')->assertHasErrors('retensiHari');
        $k->set('retensiHari', '30')->set('simpanMinimal', '0')->call('simpanPengaturan')->assertHasErrors('simpanMinimal');
        $k->set('simpanMinimal', '5')->set('tipeOtomatis', 'file')->call('simpanPengaturan')->assertHasErrors('tipeOtomatis');
        $this->assertNull(Pengaturan::ambil('backup'));

        $k->set('tipeOtomatis', 'database')->set('otomatis', true)->call('batalkanPengaturan')->assertSet('otomatis', false);
    }

    public function test_bersihkan_sekarang_menampilkan_jumlah_lalu_menghapus_hanya_yang_melewati_retensi(): void
    {
        app(PembuatBackup::class)->direktori();
        Backup::factory()->count(6)->sequence(...array_map(fn ($h) => ['created_at' => now()->subDays($h)], [200, 190, 180, 170, 160, 150]))->create();
        $k = $this->halaman()->call('tanyaRetensi')->assertSet('dialogRetensi', true)->assertSee('1 backup akan dihapus permanen');

        $k->call('terapkanRetensi')->assertSet('dialogRetensi', false);
        $this->assertSame(5, Backup::count());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'retensi_backup']);
    }

    // ------------------------------------------------------------------ uji pulih

    public function test_uji_pulih_dinonaktifkan_dengan_penjelasan_tanpa_hak_createdb(): void
    {
        $this->proses['psql_createdb'] = 'f';
        $b = $this->backupNyata();

        $k = $this->halaman()->assertSee('Uji pulihkan dinonaktifkan')->assertSee('CREATEDB');
        $k->call('tanyaUji', $b->id)->assertSet('dialogUji', false)->assertDispatched('toast', fn ($n, $p) => $p['tipe'] === 'galat' && str_contains($p['pesan'], 'CREATEDB'));
        $k->call('ujiPulih')->assertSet('hasilUji', []);
        $this->assertEmpty(array_filter($this->perintahJalan('psql'), fn ($c) => str_contains(implode(' ', $c), 'CREATE DATABASE')));
    }

    public function test_uji_pulih_berjalan_ke_database_sementara_dan_menampilkan_bukti(): void
    {
        $admin = User::factory()->admin()->create();
        $b = $this->backupNyata();

        $k = $this->halaman($admin)->assertSee('Uji pulihkan tersedia')->call('tanyaUji', $b->id)->assertSet('dialogUji', true);
        $k->call('ujiPulih')->assertSee('42')->assertSee('1.234')->assertSee('Database sementara sudah dihapus');

        $this->assertTrue($b->refresh()->uji_pulih['ok']);
        $this->assertSame(1234, $b->uji_pulih['baris']);
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'uji_pulih_backup', 'admin_id' => $admin->id]);
        $this->assertTrue(collect($this->perintahJalan('psql'))->contains(fn ($c) => str_contains(implode(' ', $c), 'DROP DATABASE')));
        $k->call('tutupUji')->assertSet('dialogUji', false)->assertSet('hasilUji', []);
    }

    public function test_uji_pulih_dibatasi_dua_kali_per_jam_dan_butuh_backup_dump(): void
    {
        $admin = User::factory()->admin()->create();
        $b = $this->backupNyata();
        $k = $this->halaman($admin);
        foreach ([1, 2] as $_) {
            $k->call('tanyaUji', $b->id)->call('ujiPulih');
        }
        $k->call('tanyaUji', $b->id)->call('ujiPulih')->assertDispatched('toast', fn ($n, $p) => str_contains($p['pesan'], 'Batas 2 uji pulih'));

        $file = Backup::factory()->create(['tipe' => 'file', 'format' => 'tar-gz']);
        $k->call('tanyaUji', $file->id)->assertDispatched('toast', tipe: 'galat');
    }

    // ------------------------------------------------------------------ unduh

    public function test_unduh_mengalirkan_berkas_privat_hanya_untuk_admin_dan_mencatat(): void
    {
        $admin = User::factory()->admin()->create();
        $b = $this->backupNyata();
        $url = route('admin.backup.unduh', $b);

        $this->get($url)->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $r = $this->actingAs($admin)->get($url)->assertOk();
        $r->assertHeader('content-disposition', 'attachment; filename='.$b->berkas);
        $r->assertHeader('x-content-type-options', 'nosniff');
        $r->assertHeader('x-checksum-sha256', $b->checksum);
        $this->assertStringContainsString('no-store', (string) $r->headers->get('cache-control'));
        $this->assertSame($b->checksum, hash('sha256', file_get_contents($r->baseResponse->getFile()->getPathname())));
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'unduh_backup', 'admin_id' => $admin->id]);
    }

    public function test_unduh_menolak_backup_gagal_hilang_atau_berkas_di_luar_folder(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->get(route('admin.backup.unduh', Backup::factory()->gagal()->create()))->assertNotFound();
        $this->get(route('admin.backup.unduh', Backup::factory()->create()))->assertNotFound();
        $this->get(route('admin.backup.unduh', Backup::factory()->create(['berkas' => '../../.env'])))->assertNotFound();
        $this->get('/admin/backup/999999/unduh')->assertNotFound();
        $this->get('/admin/backup/abc/unduh')->assertNotFound();

        $b = $this->backupNyata();
        unlink($this->dirBackup.'/'.$b->berkas);
        $this->get(route('admin.backup.unduh', $b))->assertNotFound();
    }

    public function test_unduh_dibatasi_dua_puluh_per_jam(): void
    {
        $admin = User::factory()->admin()->create();
        $b = $this->backupNyata();
        $this->actingAs($admin);
        RateLimiter::clear('backup-unduh:'.$admin->id);
        foreach (range(1, 20) as $_) {
            $this->get(route('admin.backup.unduh', $b))->assertOk();
        }
        $this->get(route('admin.backup.unduh', $b))->assertStatus(429);
    }

    public function test_berkas_backup_tidak_ada_di_folder_publik_dan_bukan_url_publik(): void
    {
        $b = $this->backupNyata();

        $this->assertStringNotContainsString(public_path(), $this->dirBackup);
        $this->assertStringContainsString('/backup', route('admin.backup.unduh', $b));
        $this->assertSame(0, config('filesystems.disks.public.visibility') === 'public' && str_starts_with(config('backup.direktori'), storage_path('app/public')) ? 1 : 0);
        Cache::flush();
    }
}
