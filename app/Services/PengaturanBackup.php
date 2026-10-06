<?php

namespace App\Services;

use App\Models\Pengaturan;

/** Pengaturan backup otomatis (kunci `backup` di tabel pengaturan). Nilai di luar daftar jatuh ke bawaan. */
class PengaturanBackup
{
    public const FREKUENSI = ['harian' => 'Setiap hari, 02.00', 'mingguan' => 'Setiap Minggu, 02.00', 'bulanan' => 'Setiap tanggal 1, 02.00'];

    public const RETENSI_HARI = [7, 14, 30, 60, 90];

    public const SIMPAN_MINIMAL = [3, 5, 10, 20];

    public const TIPE_OTOMATIS = ['database' => 'Database', 'penuh' => 'Penuh (database + file)'];

    public const BAWAAN = ['otomatis' => false, 'frekuensi' => 'harian', 'retensi_hari' => 30, 'simpan_minimal' => 5, 'tipe' => 'database'];

    /** @return array{otomatis:bool,frekuensi:string,retensi_hari:int,simpan_minimal:int,tipe:string} */
    public static function ambil(): array
    {
        $t = (array) Pengaturan::ambil('backup', []);
        $retensi = (int) ($t['retensi_hari'] ?? 0);
        $minimal = (int) ($t['simpan_minimal'] ?? 0);

        return [
            'otomatis' => (bool) ($t['otomatis'] ?? false),
            'frekuensi' => isset(self::FREKUENSI[$t['frekuensi'] ?? '']) ? $t['frekuensi'] : self::BAWAAN['frekuensi'],
            'retensi_hari' => in_array($retensi, self::RETENSI_HARI, true) ? $retensi : self::BAWAAN['retensi_hari'],
            'simpan_minimal' => in_array($minimal, self::SIMPAN_MINIMAL, true) ? $minimal : self::BAWAAN['simpan_minimal'],
            'tipe' => isset(self::TIPE_OTOMATIS[$t['tipe'] ?? '']) ? $t['tipe'] : self::BAWAAN['tipe'],
        ];
    }

    /** Dipakai penjadwal tiap hari pukul 02.00: apakah backup otomatis harus jalan hari ini. */
    public static function jalanHariIni(): bool
    {
        try {
            $p = self::ambil();
        } catch (\Throwable) {
            return false;
        }
        if (! $p['otomatis']) {
            return false;
        }

        return match ($p['frekuensi']) {
            'mingguan' => now()->isSunday(),
            'bulanan' => now()->day === 1,
            default => true,
        };
    }
}
