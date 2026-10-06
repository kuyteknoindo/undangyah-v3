<?php

namespace Tests\Concerns;

use App\Services\PembuatBackup;
use App\Services\PenjalanProses;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

/** Perlengkapan tes backup: folder sementara, koneksi pgsql palsu, dan Process::fake agar pg_dump/psql nyata TIDAK pernah jalan. */
trait SiapkanBackupUji
{
    protected string $dirBackup;

    protected const SANDI_UJI = 'sandi-rahasia-uji-789';

    /** @var array<string,mixed> perilaku proses palsu yang bisa diubah per tes */
    protected array $proses = [];

    /** @var list<list<string>> */
    protected array $dijalankan = [];

    protected function siapkanBackup(): void
    {
        $this->dirBackup = storage_path('framework/testing/backup-'.bin2hex(random_bytes(4)));
        config([
            'backup.direktori' => $this->dirBackup,
            'backup.koneksi' => 'pgsql_uji',
            'backup.disk_file' => ['media' => null],
            'database.connections.pgsql_uji' => [
                'driver' => 'pgsql', 'host' => 'db.uji.local', 'port' => '5432', 'database' => 'undangyah_uji_sumber',
                'username' => 'pengguna_uji', 'password' => self::SANDI_UJI,
            ],
        ]);
        $this->proses = ['psql_createdb' => 't', 'hitung' => '42|1234'];
        $this->app->bind(PembuatBackup::class, fn () => new PembuatBackupUji(app(PenjalanProses::class)));
        PembuatBackupUji::$bebas = 10_000_000_000;
        PembuatBackupUji::$ukuranDb = 5_000_000;
        $this->fakeProses();
    }

    protected function bersihkanBackup(): void
    {
        if (isset($this->dirBackup) && is_dir($this->dirBackup)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dirBackup, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() && ! $f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($this->dirBackup);
        }
    }

    protected function fakeProses(): void
    {
        Process::fake(function (PendingProcess $p): ProcessResult {
            $cmd = (array) $p->command;
            $this->dijalankan[] = $cmd;
            $biner = basename((string) $cmd[0]);
            $arg = fn (string $awalan) => collect($cmd)->first(fn ($a) => str_starts_with((string) $a, $awalan));

            if ($biner === 'pg_dump') {
                if (isset($this->proses['pg_dump_gagal'])) {
                    return Process::result(errorOutput: $this->proses['pg_dump_gagal'], exitCode: $this->proses['pg_dump_kode'] ?? 1);
                }
                file_put_contents(substr($arg('--file='), 7), 'PGDMP-palsu-'.str_repeat('x', 200));

                return Process::result(exitCode: 0);
            }
            if ($biner === 'pg_restore' && in_array('--list', $cmd, true)) {
                return isset($this->proses['restore_list_gagal']) ? Process::result(errorOutput: 'rusak', exitCode: 1) : Process::result(output: "; Archive created\n1; 2615 public\n", exitCode: 0);
            }
            if ($biner === 'pg_restore') {
                return isset($this->proses['restore_gagal']) ? Process::result(errorOutput: 'error: tabel rusak '.self::SANDI_UJI, exitCode: 1) : Process::result(exitCode: 0);
            }
            if ($biner === 'psql') {
                $sql = (string) substr((string) $arg('--command='), 10);
                if (str_contains($sql, 'rolcreatedb')) {
                    return Process::result(output: $this->proses['psql_createdb']."\n", exitCode: 0);
                }
                if (str_contains($sql, 'query_to_xml')) {
                    return Process::result(output: $this->proses['hitung']."\n", exitCode: 0);
                }

                return Process::result(exitCode: 0);
            }

            return Process::result(errorOutput: 'tidak dikenal', exitCode: 127);
        });
    }

    /** @return list<list<string>> semua perintah yang dijalankan, sebagai larik */
    protected function perintahJalan(?string $biner = null): array
    {
        $hasil = [];
        foreach ($this->dijalankan as $cmd) {
            if ($biner === null || basename((string) $cmd[0]) === $biner) {
                $hasil[] = $cmd;
            }
        }

        return $hasil;
    }
}

/** PembuatBackup dengan ruang disk dan ukuran database yang bisa diatur tes. */
class PembuatBackupUji extends PembuatBackup
{
    public static int $bebas = 10_000_000_000;

    public static int $ukuranDb = 5_000_000;

    protected function ruangBebas(): int
    {
        return self::$bebas;
    }

    public function ukuranDatabase(): int
    {
        return self::$ukuranDb;
    }
}
