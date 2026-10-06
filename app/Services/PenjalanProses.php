<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Satu-satunya pintu menjalankan biner luar (pg_dump, pg_restore, psql) untuk fitur backup. Dibungkus agar tes
 * cukup memakai Process::fake(). Perintah selalu berupa larik (tanpa shell); sandi hanya lewat environment.
 */
class PenjalanProses
{
    /**
     * @param  list<string>  $perintah
     * @param  array<string,string>  $env
     */
    public function jalankan(array $perintah, array $env = [], ?int $timeout = null): ProcessResult
    {
        try {
            return Process::env($env)->timeout($timeout ?? (int) config('backup.batas_waktu_detik'))->run($perintah);
        } catch (Throwable $e) {
            throw new BackupGagal('Proses '.basename((string) ($perintah[0] ?? 'luar')).' tidak bisa dijalankan atau melewati batas waktu.', 0, $e);
        }
    }

    /** Ringkas keluaran galat untuk pesan ke admin: buang rahasia, rapikan, potong. */
    public static function ringkas(string $teks, array $rahasia = [], int $maks = 300): string
    {
        foreach (array_filter($rahasia, fn ($r) => is_string($r) && strlen($r) >= 3) as $r) {
            $teks = str_replace($r, '***', $teks);
        }
        $teks = trim(preg_replace('/\s+/', ' ', $teks) ?? '');

        return mb_strlen($teks) > $maks ? mb_substr($teks, 0, $maks).'...' : $teks;
    }
}
