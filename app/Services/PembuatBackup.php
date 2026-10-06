<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Backup database (pg_dump -Fc; SQLite untuk dev/tes), arsip file unggahan, dan gabungan keduanya ke folder privat.
 * Layanan ini TIDAK PERNAH memulihkan ke database hidup (lihat UjiPulihBackup untuk uji di database sementara).
 * Nama berkas dibuat sistem; nama dari input hanya menjadi label tampilan.
 */
class PembuatBackup
{
    /** Satu-satunya bentuk nama berkas yang dikenali (mencegah path traversal lewat kolom `berkas`). */
    private const POLA_BERKAS = '/^backup-(database|file|penuh)-\d{8}-\d{6}-[a-z0-9]{6}\.(dump|sqlite\.gz|tar\.gz|tar)$/';

    private const POLA_LABEL = '/^[A-Za-z0-9._\- ]{1,60}$/';

    public function __construct(private readonly PenjalanProses $proses) {}

    // ------------------------------------------------------------------ konfigurasi dan jalur

    public function direktori(): string
    {
        $dir = (string) config('backup.direktori');
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        @chmod($dir, 0700);

        return $dir;
    }

    public function koneksi(): string
    {
        return (string) (config('backup.koneksi') ?: config('database.default'));
    }

    /** @return array<string,mixed> */
    public function konfigDb(): array
    {
        return (array) config('database.connections.'.$this->koneksi(), []);
    }

    public function driver(): string
    {
        return (string) ($this->konfigDb()['driver'] ?? '');
    }

    /** Jalur absolut berkas backup, atau null bila nama tidak sah, berkas hilang, tautan simbolik, atau di luar folder backup. */
    public function jalur(Backup $backup): ?string
    {
        $nama = $backup->berkas;
        if (! is_string($nama) || ! preg_match(self::POLA_BERKAS, $nama)) {
            return null;
        }
        $dir = realpath($this->direktori());
        $p = $dir ? $dir.DIRECTORY_SEPARATOR.$nama : null;
        if (! $p || is_link($p)) {
            return null;
        }
        $nyata = realpath($p);

        return $nyata && is_file($nyata) && str_starts_with($nyata, $dir.DIRECTORY_SEPARATOR) ? $nyata : null;
    }

    // ------------------------------------------------------------------ disk

    protected function ruangBebas(): int
    {
        return (int) (@disk_free_space($this->direktori()) ?: 0);
    }

    /** @return array{bebas:int,total:int} */
    public function ringkasanDisk(): array
    {
        $dir = $this->direktori();

        return ['bebas' => (int) (@disk_free_space($dir) ?: 0), 'total' => (int) (@disk_total_space($dir) ?: 0)];
    }

    /** Ukuran database sekarang dalam byte; 0 bila tidak bisa dibaca. */
    public function ukuranDatabase(): int
    {
        try {
            $k = $this->konfigDb();
            if (($k['driver'] ?? '') === 'pgsql') {
                return (int) DB::connection($this->koneksi())->selectOne('select pg_database_size(current_database()) as u')->u;
            }
            if (($k['driver'] ?? '') === 'sqlite' && is_file((string) ($k['database'] ?? ''))) {
                return (int) filesize($k['database']);
            }
        } catch (Throwable) {
        }

        return 0;
    }

    /** Perkiraan batas atas ukuran hasil backup (dump -Fc selalu lebih kecil dari ukuran database). */
    protected function perkiraanUkuran(string $tipe): int
    {
        $db = in_array($tipe, ['database', 'penuh'], true) ? max($this->ukuranDatabase(), 1_048_576) : 0;
        $file = in_array($tipe, ['file', 'penuh'], true) ? $this->ukuranFile() : 0;

        return $db + $file;
    }

    /**
     * Folder unggahan yang ada di konfigurasi saat ini. Disk yang belum dikonfigurasi (mis. `media`) dilewati dan dicatat.
     *
     * @return array{sumber: list<array{disk:string,root:string,sub:string}>, dilewati: list<string>}
     */
    public function sumberFile(): array
    {
        $sumber = [];
        $dilewati = [];
        $terlihat = [];
        foreach ((array) config('backup.disk_file') as $disk => $subs) {
            $cfg = config('filesystems.disks.'.$disk);
            $root = is_array($cfg) && ($cfg['driver'] ?? '') === 'local' && ! empty($cfg['root']) ? realpath((string) $cfg['root']) : false;
            if (! $root) {
                $dilewati[] = (string) $disk;

                continue;
            }
            foreach ($subs === null ? [''] : (array) $subs as $sub) {
                $dir = realpath($root.($sub === '' ? '' : DIRECTORY_SEPARATOR.$sub));
                if (! $dir || ! is_dir($dir) || ! ($dir === $root || str_starts_with($dir, $root.DIRECTORY_SEPARATOR)) || isset($terlihat[$dir])) {
                    continue;
                }
                $terlihat[$dir] = true;
                $sumber[] = ['disk' => (string) $disk, 'root' => $root, 'sub' => (string) $sub];
            }
        }

        return ['sumber' => $sumber, 'dilewati' => $dilewati];
    }

    /** @return \Generator<string,string> jalur-relatif-dalam-arsip => jalur absolut */
    private function berkasUnggahan(): \Generator
    {
        foreach ($this->sumberFile()['sumber'] as $s) {
            $awal = $s['root'].($s['sub'] === '' ? '' : DIRECTORY_SEPARATOR.$s['sub']);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($awal, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $info) {
                if ($info->isLink() || ! $info->isFile() || ! $info->isReadable()) {
                    continue;
                }
                $abs = $info->getPathname();
                $rel = substr($abs, strlen($s['root']) + 1);
                if (str_contains($rel, 'livewire-tmp')) {
                    continue;
                }
                yield $s['disk'].'/'.$rel => $abs;
            }
        }
    }

    public function ukuranFile(): int
    {
        $jumlah = 0;
        foreach ($this->berkasUnggahan() as $abs) {
            $jumlah += (int) @filesize($abs);
        }

        return $jumlah;
    }

    // ------------------------------------------------------------------ membuat backup

    private function ekstensi(string $tipe): string
    {
        if ($tipe === 'file') {
            return 'tar.gz';
        }
        if ($tipe === 'penuh') {
            return 'tar';
        }

        return $this->driver() === 'sqlite' ? 'sqlite.gz' : 'dump';
    }

    /**
     * Validasi, cek ruang disk, lalu buat baris berstatus "berjalan". Belum mengerjakan apa pun; lihat jalankan().
     *
     * @throws BackupGagal
     */
    public function mulai(string $tipe, ?User $oleh = null, ?string $label = null, string $sumber = 'manual'): Backup
    {
        if (! isset(Backup::TIPE[$tipe])) {
            throw new BackupGagal('Tipe backup tidak dikenal.');
        }
        $label = trim((string) $label);
        if ($label !== '' && ! preg_match(self::POLA_LABEL, $label)) {
            throw new BackupGagal('Nama backup hanya boleh huruf, angka, spasi, strip, titik, dan garis bawah (maks. 60 karakter).');
        }
        if (in_array($tipe, ['database', 'penuh'], true) && ! in_array($this->driver(), ['pgsql', 'sqlite'], true)) {
            throw new BackupGagal('Driver database ini belum didukung untuk backup.');
        }

        try {
            return Cache::lock('backup:mulai', 15)->block(5, function () use ($tipe, $oleh, $label, $sumber): Backup {
                Backup::where('status', Backup::BERJALAN)->where('created_at', '<', now()->subHours(2))
                    ->update(['status' => Backup::GAGAL, 'pesan_galat' => 'Terhenti: tidak selesai dalam 2 jam.', 'selesai_at' => now()]);
                if (Backup::where('status', Backup::BERJALAN)->exists()) {
                    throw new BackupGagal('Sudah ada backup yang sedang berjalan. Tunggu sampai selesai.');
                }

                $perlu = (int) ceil((float) config('backup.faktor_ruang', 2) * $this->perkiraanUkuran($tipe));
                $bebas = $this->ruangBebas();
                if ($bebas < $perlu) {
                    throw new BackupGagal('Ruang disk tidak cukup: bebas '.self::ukuran($bebas).', dibutuhkan minimal '.self::ukuran($perlu).' (2x perkiraan ukuran).');
                }

                $stempel = now()->format('Ymd-His');

                return Backup::create([
                    'tipe' => $tipe,
                    'nama' => $label !== '' ? $label : 'backup-'.$tipe.'-'.$stempel,
                    'berkas' => 'backup-'.$tipe.'-'.$stempel.'-'.Str::lower(Str::random(6)).'.'.$this->ekstensi($tipe),
                    'status' => Backup::BERJALAN,
                    'sumber' => $sumber,
                    'dibuat_oleh' => $oleh?->id,
                ]);
            });
        } catch (LockTimeoutException) {
            throw new BackupGagal('Backup lain sedang dimulai. Coba lagi sebentar lagi.');
        }
    }

    /** Kerjakan backup yang sudah dibuat mulai(). Tidak melempar: kegagalan dicatat di baris dan dikembalikan. */
    public function jalankan(Backup $backup): Backup
    {
        if ($backup->status !== Backup::BERJALAN) {
            return $backup;
        }
        @set_time_limit(0);
        $dir = $this->direktori();
        $tujuan = $dir.DIRECTORY_SEPARATOR.(string) $backup->berkas;
        $kerja = null;

        try {
            if (! preg_match(self::POLA_BERKAS, (string) $backup->berkas)) {
                throw new BackupGagal('Nama berkas backup tidak sah.');
            }
            $catatan = [];
            $format = match ($backup->tipe) {
                'database' => $this->buatDatabase($tujuan),
                'file' => $this->buatFile($tujuan, $catatan),
                'penuh' => $this->buatPenuh($tujuan, $dir, $kerja, $catatan),
                default => throw new BackupGagal('Tipe backup tidak dikenal.'),
            };

            if (! is_file($tujuan) || filesize($tujuan) === 0) {
                throw new BackupGagal('Berkas hasil backup kosong.');
            }
            @chmod($tujuan, 0600);
            $backup->update([
                'status' => Backup::SUKSES,
                'format' => $format,
                'ukuran' => filesize($tujuan),
                'checksum' => hash_file('sha256', $tujuan),
                'catatan' => $catatan ? implode(' ', $catatan) : null,
                'pesan_galat' => null,
                'selesai_at' => now(),
            ]);
        } catch (Throwable $e) {
            @unlink($tujuan);
            if (! $e instanceof BackupGagal) {
                report($e);
            }
            $backup->update([
                'status' => Backup::GAGAL,
                'pesan_galat' => $e instanceof BackupGagal ? $e->getMessage() : 'Galat tak terduga saat membuat backup; lihat log aplikasi.',
                'ukuran' => 0,
                'checksum' => null,
                'selesai_at' => now(),
            ]);
        } finally {
            if ($kerja) {
                $this->hapusFolder($kerja);
            }
        }

        Log::info('Backup selesai', ['id' => $backup->id, 'tipe' => $backup->tipe, 'status' => $backup->status, 'oleh' => $backup->dibuat_oleh]);

        return $backup->refresh();
    }

    public function buat(string $tipe, ?User $oleh = null, ?string $label = null, string $sumber = 'manual'): Backup
    {
        return $this->jalankan($this->mulai($tipe, $oleh, $label, $sumber));
    }

    private function buatDatabase(string $tujuan): string
    {
        return match ($this->driver()) {
            'pgsql' => $this->dumpPostgres($tujuan),
            'sqlite' => $this->dumpSqlite($tujuan),
            default => throw new BackupGagal('Driver database ini belum didukung untuk backup.'),
        };
    }

    private function dumpPostgres(string $tujuan): string
    {
        $c = $this->konfigDb();
        $sandi = (string) ($c['password'] ?? '');
        $perintah = [
            (string) config('backup.pg_dump'), '--format=custom', '--no-owner', '--no-acl',
            '--host='.($c['host'] ?? '127.0.0.1'), '--port='.($c['port'] ?? 5432),
            '--username='.($c['username'] ?? ''), '--dbname='.($c['database'] ?? ''), '--file='.$tujuan,
        ];
        $env = ['PGCONNECT_TIMEOUT' => '15'] + ($sandi !== '' ? ['PGPASSWORD' => $sandi] : []);

        $r = $this->proses->jalankan($perintah, $env);
        if ($r->failed()) {
            if ($r->exitCode() === 127) {
                throw new BackupGagal('Biner pg_dump tidak ditemukan di server.');
            }
            throw new BackupGagal('pg_dump gagal (kode '.$r->exitCode().'): '.PenjalanProses::ringkas($r->errorOutput(), [$sandi]));
        }

        return 'pgdump-custom';
    }

    /** Jalur dev/tes: salin konsisten lewat VACUUM INTO lalu kompres. */
    private function dumpSqlite(string $tujuan): string
    {
        $berkasDb = (string) ($this->konfigDb()['database'] ?? '');
        if ($berkasDb === '' || $berkasDb === ':memory:' || ! is_file($berkasDb)) {
            throw new BackupGagal('Database SQLite ini berada di memori atau tidak ditemukan, tidak bisa dicadangkan.');
        }
        $sementara = $tujuan.'.tmp';
        @unlink($sementara);
        try {
            DB::connection($this->koneksi())->statement('VACUUM INTO ?', [$sementara]);
            $this->kompres($sementara, $tujuan);
        } finally {
            @unlink($sementara);
        }

        return 'sqlite-gz';
    }

    private function kompres(string $sumber, string $tujuan): void
    {
        $in = fopen($sumber, 'rb');
        $out = gzopen($tujuan, 'wb6');
        if (! $in || ! $out) {
            throw new BackupGagal('Tidak bisa menulis berkas backup.');
        }
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1_048_576));
        }
        fclose($in);
        gzclose($out);
    }

    /** @param  list<string>  $catatan */
    private function buatFile(string $tujuan, array &$catatan): string
    {
        $dilewati = $this->sumberFile()['dilewati'];
        if ($dilewati) {
            $catatan[] = 'Disk belum ada di konfigurasi, dilewati: '.implode(', ', $dilewati).'.';
        }
        if ($this->arsipkanFile($tujuan) === 0) {
            throw new BackupGagal('Tidak ada file unggahan untuk dicadangkan'.($dilewati ? ' (disk '.implode(', ', $dilewati).' belum dikonfigurasi).' : '.'));
        }

        return 'tar-gz';
    }

    /** @param  list<string>  $catatan */
    private function buatPenuh(string $tujuan, string $dir, ?string &$kerja, array &$catatan): string
    {
        $kerja = $dir.DIRECTORY_SEPARATOR.'.kerja-'.Str::lower(Str::random(10));
        mkdir($kerja, 0700);
        $dbBerkas = $kerja.DIRECTORY_SEPARATOR.($this->driver() === 'sqlite' ? 'database.sqlite.gz' : 'database.dump');
        $this->buatDatabase($dbBerkas);

        $unggahan = $kerja.DIRECTORY_SEPARATOR.'unggahan.tar.gz';
        $dilewati = $this->sumberFile()['dilewati'];
        if ($dilewati) {
            $catatan[] = 'Disk belum ada di konfigurasi, dilewati: '.implode(', ', $dilewati).'.';
        }
        $jumlah = $this->arsipkanFile($unggahan);
        if ($jumlah === 0) {
            $catatan[] = 'Tidak ada file unggahan; arsip hanya berisi database.';
        }

        @unlink($tujuan);
        $tar = new PharData($tujuan);
        $tar->addFile($dbBerkas, basename($dbBerkas));
        if ($jumlah > 0) {
            $tar->addFile($unggahan, 'unggahan.tar.gz');
        }
        unset($tar);

        return 'tar';
    }

    /** Buat .tar.gz dari folder unggahan; mengembalikan jumlah file (0 = tidak ada, berkas tidak dibuat). */
    private function arsipkanFile(string $tujuanGz): int
    {
        $tar = substr($tujuanGz, 0, -3);
        @unlink($tar);
        @unlink($tujuanGz);
        $phar = new PharData($tar);
        $n = 0;
        foreach ($this->berkasUnggahan() as $rel => $abs) {
            $phar->addFile($abs, $rel);
            $n++;
        }
        if ($n === 0) {
            unset($phar);
            @unlink($tar);

            return 0;
        }
        $phar->compress(Phar::GZ);
        unset($phar);
        @unlink($tar);

        return $n;
    }

    private function hapusFolder(string $dir): void
    {
        if (! is_dir($dir) || ! str_contains(basename($dir), '.kerja-') && ! str_contains(basename($dir), '.verif-')) {
            return;
        }
        foreach (glob($dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    // ------------------------------------------------------------------ verifikasi

    /**
     * Hitung ulang checksum dan periksa struktur berkas (pg_restore --list untuk dump custom). Hasil disimpan di baris.
     *
     * @return array{ok:bool,checksum:bool,struktur:?bool,pesan:string}
     */
    public function verifikasi(Backup $backup): array
    {
        $hasil = ['ok' => false, 'checksum' => false, 'struktur' => null, 'pesan' => ''];
        $jalur = $backup->sukses() ? $this->jalur($backup) : null;
        $kerja = null;

        try {
            if (! $jalur) {
                throw new BackupGagal($backup->sukses() ? 'Berkas backup tidak ditemukan di server.' : 'Hanya backup yang sukses yang bisa diverifikasi.');
            }
            $hasil['checksum'] = $backup->checksum !== null && hash_equals($backup->checksum, (string) hash_file('sha256', $jalur));
            if (! $hasil['checksum']) {
                throw new BackupGagal('Checksum tidak cocok: berkas berubah atau rusak sejak dibuat.');
            }

            $hasil['struktur'] = match ($backup->format) {
                'pgdump-custom' => $this->periksaDump($jalur),
                'sqlite-gz' => $this->periksaSqlite($jalur),
                'tar-gz' => $this->periksaTar($jalur),
                'tar' => $this->periksaPenuh($jalur, $kerja),
                default => throw new BackupGagal('Format backup tidak dikenal.'),
            };
            $hasil['ok'] = true;
            $hasil['pesan'] = 'Checksum cocok dan struktur berkas valid.';
        } catch (BackupGagal $e) {
            $hasil['pesan'] = $e->getMessage();
            if ($hasil['checksum'] && $hasil['struktur'] === null) {
                $hasil['struktur'] = false;
            }
        } catch (Throwable $e) {
            report($e);
            $hasil['pesan'] = 'Berkas backup tidak bisa dibaca (rusak?).';
            $hasil['struktur'] = false;
        } finally {
            if ($kerja) {
                $this->hapusFolder($kerja);
            }
        }

        $backup->update(['diverifikasi_at' => now(), 'verifikasi' => $hasil]);

        return $hasil;
    }

    private function periksaDump(string $berkas): bool
    {
        $r = $this->proses->jalankan([(string) config('backup.pg_restore'), '--list', $berkas], [], 300);
        if ($r->failed() || trim($r->output()) === '') {
            throw new BackupGagal('pg_restore --list gagal: dump tidak bisa dibaca'.($r->exitCode() === 127 ? ' (biner pg_restore tidak ditemukan).' : '.'));
        }

        return true;
    }

    private function periksaSqlite(string $berkas): bool
    {
        $gz = gzopen($berkas, 'rb');
        $kepala = $gz ? (string) gzread($gz, 16) : '';
        $gz && gzclose($gz);
        if (! str_starts_with($kepala, 'SQLite format 3')) {
            throw new BackupGagal('Isi berkas bukan database SQLite yang valid.');
        }

        return true;
    }

    private function periksaTar(string $berkas): bool
    {
        if (iterator_count(new PharData($berkas)) < 1) {
            throw new BackupGagal('Arsip kosong.');
        }

        return true;
    }

    private function periksaPenuh(string $berkas, ?string &$kerja): bool
    {
        $tar = new PharData($berkas);
        if (isset($tar['database.dump'])) {
            $kerja = $this->direktori().DIRECTORY_SEPARATOR.'.verif-'.Str::lower(Str::random(10));
            mkdir($kerja, 0700);
            $tar->extractTo($kerja, 'database.dump', true);

            return $this->periksaDump($kerja.DIRECTORY_SEPARATOR.'database.dump');
        }
        if (isset($tar['database.sqlite.gz'])) {
            return true;
        }
        throw new BackupGagal('Arsip penuh tidak memuat berkas database.');
    }

    // ------------------------------------------------------------------ hapus dan retensi

    public function hapus(Backup $backup): void
    {
        if ($backup->status === Backup::BERJALAN) {
            throw new BackupGagal('Backup yang sedang berjalan tidak bisa dihapus.');
        }
        if ($jalur = $this->jalur($backup)) {
            @unlink($jalur);
        }
        $backup->delete();
    }

    /**
     * Hapus backup yang lebih tua dari $hari, kecuali $simpanMinimal backup sukses terbaru (selalu dilindungi, minimal 1)
     * dan yang sedang berjalan. Dengan $kering hanya menghitung.
     *
     * @return Collection<int,Backup> yang dihapus (atau akan dihapus)
     */
    public function terapkanRetensi(int $hari, int $simpanMinimal, bool $kering = false): Collection
    {
        $hari = max(1, $hari);
        $lindungi = Backup::where('status', Backup::SUKSES)->orderByDesc('id')->limit(max(1, $simpanMinimal))->pluck('id');
        $target = Backup::where('status', '!=', Backup::BERJALAN)
            ->where('created_at', '<', Carbon::now()->subDays($hari))
            ->whereNotIn('id', $lindungi)
            ->orderBy('id')->get();
        if (! $kering) {
            $target->each(fn (Backup $b) => $this->hapus($b));
        }

        return $target;
    }

    public static function ukuran(int|float $byte): string
    {
        $satuan = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $byte;
        while ($n >= 1024 && $i < count($satuan) - 1) {
            $n /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $n : number_format($n, $n < 10 ? 1 : 0, ',', '.')).' '.$satuan[$i];
    }
}
