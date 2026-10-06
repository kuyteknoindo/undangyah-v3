<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\BackupGagal;
use App\Services\PembuatBackup;
use App\Services\PengaturanBackup;
use App\Services\UjiPulihBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PharData;
use Tests\Concerns\PembuatBackupUji;
use Tests\Concerns\SiapkanBackupUji;
use Tests\TestCase;

class PembuatBackupTest extends TestCase
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
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pembuat(): PembuatBackup
    {
        return app(PembuatBackup::class);
    }

    private function dirMedia(array $berkas = ['foto/a.jpg' => 'isi-a', 'b.txt' => 'isi-b']): string
    {
        $dir = storage_path('framework/testing/media-'.bin2hex(random_bytes(4)));
        foreach ($berkas as $rel => $isi) {
            @mkdir(dirname($dir.'/'.$rel), 0777, true);
            file_put_contents($dir.'/'.$rel, $isi);
        }
        config(['filesystems.disks.media' => ['driver' => 'local', 'root' => $dir]]);

        return $dir;
    }

    public function test_backup_database_pgsql_membuat_dump_berizin_600_dengan_checksum(): void
    {
        $admin = User::factory()->admin()->create();
        $b = $this->pembuat()->buat('database', $admin);

        $this->assertSame(Backup::SUKSES, $b->status);
        $this->assertSame('pgdump-custom', $b->format);
        $this->assertSame($admin->id, $b->dibuat_oleh);
        $jalur = $this->dirBackup.'/'.$b->berkas;
        $this->assertFileExists($jalur);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($jalur)), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->dirBackup)), -4));
        $this->assertSame(hash_file('sha256', $jalur), $b->checksum);
        $this->assertSame(filesize($jalur), $b->ukuran);
        $this->assertNotNull($b->selesai_at);
        $this->assertMatchesRegularExpression('/^backup-database-\d{8}-\d{6}-[a-z0-9]{6}\.dump$/', $b->berkas);
    }

    public function test_sandi_hanya_lewat_environment_dan_tidak_ada_di_perintah_atau_catatan(): void
    {
        $b = $this->pembuat()->buat('database');

        $perintah = $this->perintahJalan('pg_dump')[0];
        $this->assertContains('--format=custom', $perintah);
        $this->assertContains('--username=pengguna_uji', $perintah);
        $this->assertStringNotContainsString(self::SANDI_UJI, implode(' ', $perintah));
        Process::assertRan(fn ($p) => ($p->environment['PGPASSWORD'] ?? null) === self::SANDI_UJI);
        $this->assertStringNotContainsString(self::SANDI_UJI, json_encode($b->toArray()));
    }

    public function test_pg_dump_gagal_dicatat_tanpa_sandi_dan_tanpa_berkas_sisa(): void
    {
        $this->proses['pg_dump_gagal'] = 'pg_dump: error: connection failed password='.self::SANDI_UJI;
        $b = $this->pembuat()->buat('database');

        $this->assertSame(Backup::GAGAL, $b->status);
        $this->assertStringContainsString('pg_dump gagal', $b->pesan_galat);
        $this->assertStringNotContainsString(self::SANDI_UJI, $b->pesan_galat);
        $this->assertNull($b->checksum);
        $this->assertSame([], glob($this->dirBackup.'/backup-*') ?: []);
    }

    public function test_biner_pg_dump_tidak_ada_dilaporkan_jelas(): void
    {
        $this->proses['pg_dump_gagal'] = 'sh: pg_dump: not found';
        $this->proses['pg_dump_kode'] = 127;

        $this->assertStringContainsString('pg_dump tidak ditemukan', $this->pembuat()->buat('database')->pesan_galat);
    }

    public function test_ruang_disk_kurang_dari_dua_kali_perkiraan_ditolak_sebelum_apa_pun_dibuat(): void
    {
        PembuatBackupUji::$ukuranDb = 5_000_000;
        PembuatBackupUji::$bebas = 9_000_000;

        try {
            $this->pembuat()->mulai('database');
            $this->fail('Seharusnya ditolak.');
        } catch (BackupGagal $e) {
            $this->assertStringContainsString('Ruang disk tidak cukup', $e->getMessage());
        }
        $this->assertSame(0, Backup::count());
        Process::assertNothingRan();

        PembuatBackupUji::$bebas = 10_000_000;
        $this->assertSame('berjalan', $this->pembuat()->mulai('database')->status);
    }

    public function test_label_divalidasi_dan_nama_berkas_tidak_pernah_dari_input(): void
    {
        foreach (['../../etc/passwd', 'a/b', 'x;rm -rf', str_repeat('a', 61), "baris\nbaru"] as $buruk) {
            try {
                $this->pembuat()->mulai('database', null, $buruk);
                $this->fail("Label {$buruk} seharusnya ditolak.");
            } catch (BackupGagal) {
                $this->assertTrue(true);
            }
        }
        $b = $this->pembuat()->buat('database', null, 'sebelum impor-tema_1.0');
        $this->assertSame('sebelum impor-tema_1.0', $b->nama);
        $this->assertStringNotContainsString('impor', $b->berkas);
        $this->assertFileExists($this->dirBackup.'/'.$b->berkas);
        $this->assertSame(1, Backup::count());
    }

    public function test_tipe_tidak_dikenal_dan_hanya_satu_backup_berjalan(): void
    {
        $this->expectException(BackupGagal::class);
        try {
            $this->pembuat()->mulai('semua');
        } finally {
            $this->pembuat()->mulai('database');
            try {
                $this->pembuat()->mulai('database');
                $this->fail('Backup kedua seharusnya ditolak.');
            } catch (BackupGagal $e) {
                $this->assertStringContainsString('sedang berjalan', $e->getMessage());
            }
            $this->assertSame(1, Backup::count());
        }
    }

    public function test_backup_berjalan_yang_macet_lebih_dari_dua_jam_ditandai_gagal(): void
    {
        $lama = Backup::factory()->create(['status' => 'berjalan', 'created_at' => now()->subHours(3), 'berkas' => null]);
        $this->pembuat()->mulai('database');

        $this->assertSame(Backup::GAGAL, $lama->refresh()->status);
    }

    public function test_verifikasi_lolos_lalu_gagal_bila_berkas_diubah_atau_hilang(): void
    {
        $b = $this->pembuat()->buat('database');
        $hasil = $this->pembuat()->verifikasi($b);

        $this->assertTrue($hasil['ok']);
        $this->assertTrue($hasil['checksum']);
        $this->assertTrue($b->refresh()->verifikasi['ok']);
        $this->assertNotNull($b->diverifikasi_at);
        $this->assertContains('--list', $this->perintahJalan('pg_restore')[0]);

        file_put_contents($this->dirBackup.'/'.$b->berkas, 'diubah');
        $hasil = $this->pembuat()->verifikasi($b);
        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('Checksum tidak cocok', $hasil['pesan']);

        unlink($this->dirBackup.'/'.$b->berkas);
        $this->assertStringContainsString('tidak ditemukan', $this->pembuat()->verifikasi($b)['pesan']);
    }

    public function test_verifikasi_gagal_bila_pg_restore_list_menolak_dump(): void
    {
        $b = $this->pembuat()->buat('database');
        $this->proses['restore_list_gagal'] = true;

        $hasil = $this->pembuat()->verifikasi($b);
        $this->assertFalse($hasil['ok']);
        $this->assertTrue($hasil['checksum']);
        $this->assertFalse($hasil['struktur']);
    }

    public function test_verifikasi_backup_gagal_ditolak(): void
    {
        $b = Backup::factory()->gagal()->create();

        $this->assertFalse($this->pembuat()->verifikasi($b)['ok']);
    }

    public function test_path_traversal_pada_kolom_berkas_tidak_pernah_dibaca_atau_dihapus(): void
    {
        $luar = dirname($this->dirBackup).'/rahasia-luar.txt';
        file_put_contents($luar, 'jangan-hapus');
        $this->pembuat()->direktori();

        try {
            foreach (['../rahasia-luar.txt', '/etc/passwd', 'backup-database-20260101-000000-abcdef.dump/../../rahasia-luar.txt', '..%2F..%2F.env'] as $jahat) {
                $b = Backup::factory()->create(['berkas' => $jahat]);
                $this->assertNull($this->pembuat()->jalur($b));
                $this->pembuat()->hapus($b);
            }
            $this->assertFileExists($luar);
            $this->assertSame(0, Backup::count());
        } finally {
            @unlink($luar);
        }
    }

    public function test_tautan_simbolik_di_folder_backup_ditolak(): void
    {
        $this->pembuat()->direktori();
        $luar = dirname($this->dirBackup).'/luar-'.bin2hex(random_bytes(3)).'.dump';
        file_put_contents($luar, 'x');
        $nama = 'backup-database-20260101-000000-abcdef.dump';
        symlink($luar, $this->dirBackup.'/'.$nama);

        try {
            $this->assertNull($this->pembuat()->jalur(Backup::factory()->create(['berkas' => $nama])));
        } finally {
            @unlink($luar);
        }
    }

    public function test_hapus_menghapus_berkas_dan_baris_tetapi_menolak_yang_berjalan(): void
    {
        $b = $this->pembuat()->buat('database');
        $jalur = $this->dirBackup.'/'.$b->berkas;
        $this->pembuat()->hapus($b);
        $this->assertFileDoesNotExist($jalur);
        $this->assertModelMissing($b);

        $jalan = Backup::factory()->create(['status' => 'berjalan']);
        $this->expectException(BackupGagal::class);
        $this->pembuat()->hapus($jalan);
    }

    public function test_backup_file_membuat_tar_gz_dari_folder_unggahan_tanpa_tautan_simbolik(): void
    {
        $dir = $this->dirMedia();
        symlink('/etc/hostname', $dir.'/tautan.txt');
        $b = $this->pembuat()->buat('file');

        $this->assertSame(Backup::SUKSES, $b->status, (string) $b->pesan_galat);
        $this->assertSame('tar-gz', $b->format);
        $nama = collect(new \RecursiveIteratorIterator(new PharData($this->dirBackup.'/'.$b->berkas)))->map(fn ($f) => $f->getFileName())->sort()->values()->all();
        $this->assertSame(['a.jpg', 'b.txt'], $nama);
        $this->assertTrue($this->pembuat()->verifikasi($b)['ok']);
    }

    public function test_backup_file_tanpa_disk_media_gagal_dengan_penjelasan(): void
    {
        config(['filesystems.disks.media' => null]);
        $b = $this->pembuat()->buat('file');

        $this->assertSame(Backup::GAGAL, $b->status);
        $this->assertStringContainsString('media', $b->pesan_galat);
    }

    public function test_backup_penuh_memuat_dump_dan_arsip_file_dan_bisa_diverifikasi(): void
    {
        $this->dirMedia();
        $b = $this->pembuat()->buat('penuh');

        $this->assertSame(Backup::SUKSES, $b->status, (string) $b->pesan_galat);
        $this->assertSame('tar', $b->format);
        $tar = new PharData($this->dirBackup.'/'.$b->berkas);
        $this->assertTrue(isset($tar['database.dump']));
        $this->assertTrue(isset($tar['unggahan.tar.gz']));
        $this->assertTrue($this->pembuat()->verifikasi($b)['ok']);
        $this->assertSame([], glob($this->dirBackup.'/.kerja-*') ?: [], 'folder kerja harus dibersihkan');
        $this->assertSame([], glob($this->dirBackup.'/.verif-*') ?: []);
    }

    public function test_backup_penuh_tanpa_disk_media_tetap_berisi_database_dengan_catatan(): void
    {
        config(['filesystems.disks.media' => null]);
        $b = $this->pembuat()->buat('penuh');

        $this->assertSame(Backup::SUKSES, $b->status, (string) $b->pesan_galat);
        $this->assertStringContainsString('media', $b->catatan);
        $this->assertFalse(isset((new PharData($this->dirBackup.'/'.$b->berkas))['unggahan.tar.gz']));
    }

    public function test_jalur_sqlite_membuat_salinan_nyata_terkompresi(): void
    {
        $berkasDb = storage_path('framework/testing/uji-'.bin2hex(random_bytes(4)).'.sqlite');
        touch($berkasDb);
        config(['backup.koneksi' => 'sqlite_uji', 'database.connections.sqlite_uji' => ['driver' => 'sqlite', 'database' => $berkasDb, 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::connection('sqlite_uji')->statement('create table contoh (id integer primary key, isi text)');
        DB::connection('sqlite_uji')->table('contoh')->insert(['isi' => 'halo']);

        try {
            $b = $this->pembuat()->buat('database');
            $this->assertSame(Backup::SUKSES, $b->status, (string) $b->pesan_galat);
            $this->assertSame('sqlite-gz', $b->format);
            $this->assertStringEndsWith('.sqlite.gz', $b->berkas);
            $this->assertTrue($this->pembuat()->verifikasi($b)['ok']);
            $isi = gzdecode((string) file_get_contents($this->dirBackup.'/'.$b->berkas));
            $this->assertStringStartsWith('SQLite format 3', $isi);
            $this->assertStringContainsString('halo', $isi);
            Process::assertNothingRan();
        } finally {
            DB::purge('sqlite_uji');
            @unlink($berkasDb);
        }
    }

    public function test_sqlite_memori_tidak_bisa_dicadangkan_dengan_pesan_jelas(): void
    {
        config(['backup.koneksi' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        $b = $this->pembuat()->buat('database');

        $this->assertSame(Backup::GAGAL, $b->status);
        $this->assertStringContainsString('memori', $b->pesan_galat);
    }

    public function test_retensi_menghapus_lama_tetapi_selalu_menyisakan_terbaru_dan_yang_berjalan(): void
    {
        $this->pembuat()->direktori();
        $buat = fn (int $hariLalu, array $s = []) => Backup::factory()->create($s + ['created_at' => now()->subDays($hariLalu)]);
        $a = $buat(100);
        $b = $buat(90);
        $c = $buat(80);
        $gagalLama = $buat(95, ['status' => 'gagal', 'berkas' => null]);
        $berjalanLama = $buat(99, ['status' => 'berjalan']);
        $baru = $buat(1);
        file_put_contents($this->dirBackup.'/'.$a->berkas, 'x');

        $kering = $this->pembuat()->terapkanRetensi(30, 2, true);
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $gagalLama->id], $kering->pluck('id')->all(), 'sukses terbaru ($baru, $c) terlindungi');
        $this->assertSame(6, Backup::count(), 'kering tidak menghapus');

        $this->pembuat()->terapkanRetensi(30, 2);
        $this->assertEqualsCanonicalizing([$c->id, $baru->id, $berjalanLama->id], Backup::pluck('id')->all());
        $this->assertFileDoesNotExist($this->dirBackup.'/'.$a->berkas);

        $this->pembuat()->terapkanRetensi(1, 0);
        $this->assertTrue(Backup::whereKey($baru->id)->exists(), 'minimal satu backup sukses selalu dipertahankan');
    }

    public function test_perintah_artisan_membuat_backup_dan_melaporkan_tanpa_rahasia(): void
    {
        $this->artisan('undangyah:backup', ['--tipe' => 'database'])
            ->expectsOutputToContain('Backup database selesai')
            ->doesntExpectOutputToContain(self::SANDI_UJI)
            ->assertSuccessful();
        $this->assertSame(1, Backup::where('sumber', 'manual')->count());

        $this->proses['pg_dump_gagal'] = 'pg_dump: error';
        $this->artisan('undangyah:backup')->expectsOutputToContain('Backup gagal')->assertFailed();

        $this->artisan('undangyah:backup', ['--tipe' => 'aneh'])->assertFailed();
    }

    public function test_perintah_otomatis_memakai_pengaturan_dan_menerapkan_retensi(): void
    {
        Pengaturan::simpan('backup', ['otomatis' => true, 'frekuensi' => 'harian', 'retensi_hari' => 7, 'simpan_minimal' => 3, 'tipe' => 'database']);
        $this->pembuat()->direktori();
        foreach ([40, 39, 38, 37, 36] as $hari) {
            Backup::factory()->create(['created_at' => now()->subDays($hari)]);
        }

        $this->artisan('undangyah:backup', ['--otomatis' => true])->expectsOutputToContain('Retensi: 3 backup lama dihapus')->assertSuccessful();
        $this->assertSame(3, Backup::count());
        $this->assertSame('jadwal', Backup::orderByDesc('id')->first()->sumber);
    }

    public function test_jadwal_default_nonaktif_dan_mengikuti_frekuensi(): void
    {
        $this->assertFalse(PengaturanBackup::jalanHariIni());
        $this->assertFalse(PengaturanBackup::BAWAAN['otomatis']);

        Pengaturan::simpan('backup', ['otomatis' => true, 'frekuensi' => 'mingguan']);
        Carbon::setTestNow('2026-10-04 02:00'); // Minggu
        $this->assertTrue(PengaturanBackup::jalanHariIni());
        Carbon::setTestNow('2026-10-06 02:00'); // Selasa
        $this->assertFalse(PengaturanBackup::jalanHariIni());

        Pengaturan::simpan('backup', ['otomatis' => true, 'frekuensi' => 'bulanan']);
        Carbon::setTestNow('2026-11-01 02:00');
        $this->assertTrue(PengaturanBackup::jalanHariIni());
        Carbon::setTestNow('2026-11-02 02:00');
        $this->assertFalse(PengaturanBackup::jalanHariIni());

        Pengaturan::simpan('backup', ['otomatis' => true, 'frekuensi' => 'ngawur', 'retensi_hari' => 5]);
        $this->assertSame('harian', PengaturanBackup::ambil()['frekuensi']);
        $this->assertSame(30, PengaturanBackup::ambil()['retensi_hari']);
    }

    public function test_penjadwal_terdaftar_dan_hanya_jalan_bila_diaktifkan(): void
    {
        $acara = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'undangyah:backup'));
        $this->assertNotNull($acara);
        $this->assertSame('0 2 * * *', $acara->expression);
        $this->assertFalse($acara->filtersPass(app()));

        Pengaturan::simpan('backup', ['otomatis' => true, 'frekuensi' => 'harian']);
        $this->assertTrue($acara->filtersPass(app()));
    }

    // ------------------------------------------------------------------ uji pulih

    private function uji(): UjiPulihBackup
    {
        return app(UjiPulihBackup::class);
    }

    public function test_uji_pulih_membuat_database_sementara_menghitung_lalu_menghapusnya(): void
    {
        $b = $this->pembuat()->buat('database');
        $hasil = $this->uji()->jalankan($b);

        $this->assertTrue($hasil['ok'], $hasil['pesan']);
        $this->assertSame(42, $hasil['tabel']);
        $this->assertSame(1234, $hasil['baris']);
        $this->assertMatchesRegularExpression('/^undangyah_uji_pulih_[a-z0-9]{8}$/', $hasil['database']);
        $b->refresh();
        $this->assertTrue($b->uji_pulih['ok']);
        $this->assertNotNull($b->uji_pulih_at);

        $sql = collect($this->perintahJalan('psql'))->map(fn ($c) => (string) collect($c)->first(fn ($a) => str_starts_with($a, '--command=')))->all();
        $this->assertTrue(collect($sql)->contains(fn ($s) => str_contains($s, 'CREATE DATABASE "'.$hasil['database'].'"')));
        $this->assertStringContainsString('DROP DATABASE IF EXISTS "'.$hasil['database'].'"', end($sql));
        $restore = collect($this->perintahJalan('pg_restore'))->first(fn ($c) => ! in_array('--list', $c, true));
        $this->assertContains('--dbname='.$hasil['database'], $restore);
        $this->assertNotContains('--dbname=undangyah_uji_sumber', $restore);
        $this->assertNotContains('--clean', $restore);
        Process::assertRan(fn ($p) => ($p->environment['PGPASSWORD'] ?? null) === self::SANDI_UJI);
        $this->assertStringNotContainsString(self::SANDI_UJI, implode(' ', array_merge(...$this->perintahJalan())));
    }

    public function test_uji_pulih_tidak_pernah_menimpa_database_sumber_atau_lain(): void
    {
        $this->uji()->jalankan($this->pembuat()->buat('database'));

        foreach ($this->perintahJalan('psql') as $perintah) {
            $sql = (string) collect($perintah)->first(fn ($a) => str_starts_with($a, '--command='));
            if (preg_match('/(CREATE|DROP) DATABASE/', $sql)) {
                $this->assertMatchesRegularExpression('/DATABASE (IF EXISTS )?"undangyah_uji_pulih_[a-z0-9]{8}"/', $sql);
            }
        }
    }

    public function test_uji_pulih_menghapus_database_sementara_walau_restore_gagal(): void
    {
        $b = $this->pembuat()->buat('database');
        $this->proses['restore_gagal'] = true;
        $hasil = $this->uji()->jalankan($b);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('pg_restore', $hasil['pesan']);
        $this->assertStringNotContainsString(self::SANDI_UJI, $hasil['pesan']);
        $sql = collect($this->perintahJalan('psql'))->map(fn ($c) => (string) collect($c)->first(fn ($a) => str_starts_with($a, '--command=')));
        $this->assertTrue($sql->contains(fn ($s) => str_contains($s, 'DROP DATABASE')));
        $this->assertFalse($b->refresh()->uji_pulih['ok']);
    }

    public function test_uji_pulih_ditolak_tanpa_hak_createdb_dan_tidak_membuat_apa_pun(): void
    {
        $this->proses['psql_createdb'] = 'f';
        $b = $this->pembuat()->buat('database');

        $kem = $this->uji()->kemampuan();
        $this->assertFalse($kem['bisa']);
        $this->assertStringContainsString('CREATEDB', $kem['alasan']);
        try {
            $this->uji()->jalankan($b);
            $this->fail('Seharusnya ditolak.');
        } catch (BackupGagal) {
            $this->assertEmpty(collect($this->perintahJalan('psql'))->filter(fn ($c) => str_contains(implode(' ', $c), 'CREATE DATABASE')));
            $this->assertEmpty($this->perintahJalan('pg_restore') ? array_filter($this->perintahJalan('pg_restore'), fn ($c) => ! in_array('--list', $c, true)) : []);
        }
    }

    public function test_uji_pulih_tidak_tersedia_untuk_sqlite_dan_backup_file(): void
    {
        $this->dirMedia();
        $file = $this->pembuat()->buat('file');
        $this->assertFalse($file->bisaDiujiPulih());
        try {
            $this->uji()->jalankan($file);
            $this->fail();
        } catch (BackupGagal $e) {
            $this->assertStringContainsString('dump PostgreSQL', $e->getMessage());
        }

        config(['backup.koneksi' => 'sqlite']);
        Cache::forget('backup:kemampuan-uji');
        $this->assertFalse($this->uji()->kemampuan()['bisa']);
        $this->assertStringContainsString('PostgreSQL', $this->uji()->kemampuan()['alasan']);
    }

    public function test_uji_pulih_backup_penuh_mengekstrak_dump_lalu_membersihkan_folder_kerja(): void
    {
        $this->dirMedia();
        $b = $this->pembuat()->buat('penuh');
        $hasil = $this->uji()->jalankan($b);

        $this->assertTrue($hasil['ok'], $hasil['pesan']);
        $restore = collect($this->perintahJalan('pg_restore'))->first(fn ($c) => ! in_array('--list', $c, true));
        $this->assertStringEndsWith('/database.dump', end($restore));
        $this->assertSame([], glob($this->dirBackup.'/.kerja-*') ?: []);
    }
}
