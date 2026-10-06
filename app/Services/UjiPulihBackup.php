<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PharData;
use Throwable;

/**
 * "Uji pulihkan": memulihkan dump ke database SEMENTARA yang namanya dibuat acak (undangyah_uji_pulih_xxxxxxxx),
 * menghitung tabel dan baris sebagai bukti, lalu SELALU menghapus database itu. Tidak pernah menyentuh database hidup
 * atau database lain. Hanya berjalan bila role database punya hak CREATEDB.
 */
class UjiPulihBackup
{
    public function __construct(private readonly PenjalanProses $proses, private readonly PembuatBackup $pembuat) {}

    /**
     * Apakah uji pulih bisa dijalankan di server ini. Hasil pengecekan hak CREATEDB disimpan 5 menit.
     *
     * @return array{bisa:bool,alasan:?string}
     */
    public function kemampuan(): array
    {
        return Cache::remember('backup:kemampuan-uji', 300, function (): array {
            if ($this->pembuat->driver() !== 'pgsql') {
                return ['bisa' => false, 'alasan' => 'Uji pulihkan hanya tersedia untuk database PostgreSQL.'];
            }
            try {
                $r = $this->psql($this->pembuat->konfigDb()['database'] ?? '', 'select (rolcreatedb or rolsuper) from pg_roles where rolname = current_user');
            } catch (BackupGagal) {
                return ['bisa' => false, 'alasan' => 'Hak role database belum bisa diperiksa (psql tidak tersedia atau koneksi gagal).'];
            }
            if (trim($r) !== 't') {
                return ['bisa' => false, 'alasan' => 'Role database aplikasi tidak punya hak CREATEDB, sehingga database sementara untuk uji tidak bisa dibuat. Beri hak itu secara sengaja oleh pemilik (ALTER ROLE ... CREATEDB) bila ingin memakai fitur ini; pemulihan manual tetap bisa dilakukan lewat instruksi di bawah.'];
            }

            return ['bisa' => true, 'alasan' => null];
        });
    }

    public function lupakanKemampuan(): void
    {
        Cache::forget('backup:kemampuan-uji');
    }

    /**
     * @return array{ok:bool,database:string,tabel:int,baris:int,durasi:float,pesan:string,at:string}
     *
     * @throws BackupGagal bila syarat tidak terpenuhi sebelum database sementara dibuat
     */
    public function jalankan(Backup $backup): array
    {
        $kem = $this->kemampuan();
        if (! $kem['bisa']) {
            throw new BackupGagal((string) $kem['alasan']);
        }
        if (! $backup->bisaDiujiPulih()) {
            throw new BackupGagal('Hanya backup database (atau penuh) berformat dump PostgreSQL yang sukses yang bisa diuji.');
        }
        $jalur = $this->pembuat->jalur($backup);
        if (! $jalur) {
            throw new BackupGagal('Berkas backup tidak ditemukan di server.');
        }

        $awal = microtime(true);
        $hidup = (string) ($this->pembuat->konfigDb()['database'] ?? '');
        $nama = (string) config('backup.awalan_db_uji').'_'.Str::lower(Str::random(8));
        // Pengaman mutlak: nama harus berpola sementara dan tidak boleh sama dengan database mana pun yang dikenal.
        if (! preg_match('/^undangyah_uji_pulih_[a-z0-9]{8}$/', $nama) || $nama === $hidup) {
            throw new BackupGagal('Nama database sementara tidak sah.');
        }

        $kerja = null;
        $dibuat = false;
        $hasil = ['ok' => false, 'database' => $nama, 'tabel' => 0, 'baris' => 0, 'durasi' => 0.0, 'pesan' => '', 'at' => now()->toIso8601String()];

        try {
            if ($backup->format === 'tar') {
                $kerja = $this->pembuat->direktori().DIRECTORY_SEPARATOR.'.kerja-'.Str::lower(Str::random(10));
                mkdir($kerja, 0700);
                (new PharData($jalur))->extractTo($kerja, 'database.dump', true);
                $jalur = $kerja.DIRECTORY_SEPARATOR.'database.dump';
            }

            $this->psql($hidup, 'CREATE DATABASE "'.$nama.'"');
            $dibuat = true;

            $c = $this->pembuat->konfigDb();
            $r = $this->proses->jalankan([
                (string) config('backup.pg_restore'), '--no-owner', '--no-acl',
                '--host='.($c['host'] ?? '127.0.0.1'), '--port='.($c['port'] ?? 5432), '--username='.($c['username'] ?? ''),
                '--dbname='.$nama, $jalur,
            ], $this->env());
            if ($r->failed()) {
                throw new BackupGagal('pg_restore ke database sementara gagal (kode '.$r->exitCode().'): '.PenjalanProses::ringkas($r->errorOutput(), [$this->sandi()]));
            }

            $keluar = trim($this->psql($nama, "select count(*) || '|' || coalesce(sum((xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', table_schema, table_name), false, true, '')))[1]::text::bigint), 0) from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"));
            if (! preg_match('/^(\d+)\|(\d+)$/', $keluar, $m)) {
                throw new BackupGagal('Hasil penghitungan tabel tidak terbaca.');
            }
            if ((int) $m[1] === 0) {
                throw new BackupGagal('Pemulihan selesai tetapi tidak ada tabel di database sementara.');
            }
            $hasil['tabel'] = (int) $m[1];
            $hasil['baris'] = (int) $m[2];
            $hasil['ok'] = true;
            $hasil['pesan'] = 'Dump berhasil dipulihkan ke database sementara: '.$hasil['tabel'].' tabel, '.number_format($hasil['baris'], 0, ',', '.').' baris. Database sementara sudah dihapus.';
        } catch (BackupGagal $e) {
            $hasil['pesan'] = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $hasil['pesan'] = 'Uji pulih berhenti karena galat tak terduga; lihat log aplikasi.';
        } finally {
            if ($dibuat) {
                try {
                    $this->psql($hidup, 'DROP DATABASE IF EXISTS "'.$nama.'" WITH (FORCE)');
                } catch (Throwable $e) {
                    report($e);
                    $hasil['ok'] = false;
                    $hasil['pesan'] .= ' PERINGATAN: database sementara '.$nama.' gagal dihapus otomatis; hapus manual (DROP DATABASE).';
                }
            }
            if ($kerja) {
                @unlink($kerja.DIRECTORY_SEPARATOR.'database.dump');
                @rmdir($kerja);
            }
        }

        $hasil['durasi'] = round(microtime(true) - $awal, 1);
        $backup->update(['uji_pulih_at' => now(), 'uji_pulih' => $hasil]);

        return $hasil;
    }

    private function sandi(): string
    {
        return (string) ($this->pembuat->konfigDb()['password'] ?? '');
    }

    /** @return array<string,string> */
    private function env(): array
    {
        return ['PGCONNECT_TIMEOUT' => '15'] + ($this->sandi() !== '' ? ['PGPASSWORD' => $this->sandi()] : []);
    }

    /** Jalankan satu pernyataan SQL lewat psql ke database $db; mengembalikan keluaran tanpa header. */
    private function psql(string $db, string $sql): string
    {
        $c = $this->pembuat->konfigDb();
        $r = $this->proses->jalankan([
            (string) config('backup.psql'), '--no-psqlrc', '--tuples-only', '--no-align', '-v', 'ON_ERROR_STOP=1',
            '--host='.($c['host'] ?? '127.0.0.1'), '--port='.($c['port'] ?? 5432), '--username='.($c['username'] ?? ''),
            '--dbname='.$db, '--command='.$sql,
        ], $this->env(), 600);
        if ($r->failed()) {
            throw new BackupGagal('psql gagal (kode '.$r->exitCode().'): '.PenjalanProses::ringkas($r->errorOutput(), [$this->sandi()]));
        }

        return $r->output();
    }
}
