<?php

namespace App\Console\Commands;

use App\Services\BackupGagal;
use App\Services\PembuatBackup;
use App\Services\PengaturanBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('undangyah:backup {--tipe=database : database | file | penuh} {--label= : Nama tampilan (opsional)} {--otomatis : Pakai tipe dari pengaturan dan terapkan retensi (untuk penjadwal)} {--retensi : Terapkan retensi sesudah backup}')]
#[Description('Buat backup database/file/penuh ke storage/app/backups, lalu (opsional) hapus backup yang melewati retensi')]
class BackupSistem extends Command
{
    public function handle(PembuatBackup $pembuat): int
    {
        $pengaturan = PengaturanBackup::ambil();
        $otomatis = (bool) $this->option('otomatis');
        $tipe = $otomatis ? $pengaturan['tipe'] : (string) $this->option('tipe');

        try {
            $backup = $pembuat->buat($tipe, null, $this->option('label') ?: null, $otomatis ? 'jadwal' : 'manual');
        } catch (BackupGagal $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($backup->sukses()) {
            $this->info("Backup {$backup->tipe} selesai: {$backup->berkas} (".PembuatBackup::ukuran($backup->ukuran).', sha256 '.substr((string) $backup->checksum, 0, 12).'...)');
        } else {
            $this->error('Backup gagal: '.$backup->pesan_galat);
        }

        if ($otomatis || $this->option('retensi')) {
            $hapus = $pembuat->terapkanRetensi($pengaturan['retensi_hari'], $pengaturan['simpan_minimal']);
            $this->line("Retensi: {$hapus->count()} backup lama dihapus.");
        }

        return $backup->sukses() ? self::SUCCESS : self::FAILURE;
    }
}
