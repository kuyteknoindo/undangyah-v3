<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\PembuatBackup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Mengerjakan backup yang sudah dibuat PembuatBackup::mulai() (dipakai bila BACKUP_ANTREAN=true). */
class JalankanBackup implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $backupId) {}

    public function handle(PembuatBackup $pembuat): void
    {
        $backup = Backup::find($this->backupId);
        if ($backup) {
            $pembuat->jalankan($backup);
        }
    }
}
