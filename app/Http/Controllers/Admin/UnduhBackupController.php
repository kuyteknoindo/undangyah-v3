<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\LogAksiAdmin;
use App\Services\PembuatBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Mengalirkan berkas backup dari disk privat kepada admin. Nama berkas diambil dari catatan sistem, bukan dari input. */
class UnduhBackupController extends Controller
{
    private const BATAS_PER_JAM = 20;

    public function __invoke(Request $request, Backup $backup, PembuatBackup $pembuat): BinaryFileResponse
    {
        $admin = $request->user();
        abort_unless($admin?->isAdmin(), 403);

        $jalur = $backup->sukses() ? $pembuat->jalur($backup) : null;
        abort_unless($jalur, 404);

        $kunci = 'backup-unduh:'.$admin->id;
        abort_if(RateLimiter::tooManyAttempts($kunci, self::BATAS_PER_JAM), 429, 'Terlalu banyak unduhan. Coba lagi nanti.');
        RateLimiter::hit($kunci, 3600);

        LogAksiAdmin::catat($admin, 'unduh_backup', null, ['backup_id' => $backup->id, 'berkas' => $backup->berkas]);

        return response()->download($jalur, $backup->berkas, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Checksum-SHA256' => (string) $backup->checksum,
        ]);
    }
}
