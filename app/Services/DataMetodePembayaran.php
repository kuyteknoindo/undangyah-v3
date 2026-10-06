<?php

namespace App\Services;

use App\Models\MetodePembayaran;

/**
 * Penanda "data contoh" pada metode pembayaran. Seeder mengisi rekening PLACEHOLDER (`data.placeholder` = true); tanda ini
 * hanya boleh hilang bila admin menyimpan rekening/tujuan yang nyata.
 */
class DataMetodePembayaran
{
    public function masihPlaceholder(MetodePembayaran $metode): bool
    {
        return (bool) (($metode->data ?? [])['placeholder'] ?? false);
    }

    /** True bila isian masih berupa contoh bawaan (nomor kosong/nol semua, nama "GANTI DI ADMIN", atau "BANK CONTOH"). */
    public function terlihatContoh(string $tipe, array $data): bool
    {
        $nilai = $tipe === MetodePembayaran::INSTRUKSI ? (string) ($data['tujuan_nilai'] ?? '') : (string) ($data['nomor'] ?? '');
        $angka = preg_replace('/\D+/', '', $nilai) ?? '';
        if ($angka === '' || preg_match('/^0+$/', $angka)) {
            return true;
        }
        if (stripos((string) ($data['atas'] ?? ''), 'GANTI DI ADMIN') !== false) {
            return true;
        }

        return $tipe === MetodePembayaran::MANUAL && strtoupper(trim((string) ($data['bank'] ?? ''))) === 'BANK CONTOH';
    }
}
