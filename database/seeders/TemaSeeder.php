<?php

namespace Database\Seeders;

use App\Models\Tema;
use Illuminate\Database\Seeder;

/** Tema bawaan dari desain (TSEED di Main.dc.html). Idempoten berdasarkan kolom `kode`. */
class TemaSeeder extends Seeder
{
    public const BAWAAN = [
        ['t1', 'Biru Klasik', 'Gratis', 'publish', '#1D4ED8', '#E8C46E', '#F3F6FB', '#FFFFFF', '#10203A', 'playfair', null],
        ['t2', 'Hijau Sage', 'Gratis', 'publish', '#2F5D4B', '#D9C27A', '#EFF4F0', '#FFFFFF', '#1B2B24', 'playfair', null],
        ['t3', 'Minimalis', 'Gratis', 'publish', '#FFFFFF', '#B8893B', '#F5F6F8', '#FFFFFF', '#10203A', 'jakarta', null],
        ['t4', 'Emas Antik', 'Premium', 'publish', '#8A5A14', '#F3D58B', '#FBF6EA', '#FFFFFF', '#3A2A0F', 'playfair', null],
        ['t5', 'Rose Anggun', 'Premium', 'publish', '#A8324F', '#F6C9D3', '#FDF1F4', '#FFFFFF', '#3B1020', 'playfair', null],
        ['t6', 'Midnight', 'Premium', 'publish', '#10203A', '#E8C46E', '#17294A', '#10203A', '#FFFFFF', 'jakarta', ['global' => true, 'jenis' => 'image', 'section' => []]],
        ['t7', 'Royal Burgundy', 'Deluxe', 'publish', '#5B1530', '#E9C987', '#F9EEF1', '#FFFFFF', '#2E0B18', 'playfair', ['global' => true, 'jenis' => 'image', 'section' => []]],
        ['t8', 'Senja Tropis', 'Deluxe', 'draft', '#B4491E', '#FBE3B4', '#FFF3E4', '#FFFFFF', '#3B1A0A', 'jakarta', null],
    ];

    public function run(): void
    {
        foreach (self::BAWAAN as $i => [$kode, $nama, $tier, $status, $utama, $aksen, $permukaan, $halaman, $teks, $font, $latar]) {
            Tema::withTrashed()->firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tier' => $tier, 'status' => $status,
                'warna_utama' => $utama, 'warna_aksen' => $aksen, 'warna_permukaan' => $permukaan,
                'warna_halaman' => $halaman, 'warna_teks' => $teks, 'font_judul' => $font,
                'latar_tetap' => $latar, 'urutan' => $i + 1,
            ]);
        }
    }
}
