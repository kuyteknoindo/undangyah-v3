<?php

namespace App\Services;

use App\Models\Pengaturan;
use Illuminate\Support\Carbon;

/**
 * Kontak layanan pelanggan dari Pengaturan (kunci `kontak`). Tidak ada bawaan: pemilik wajib mengisi
 * `kontak` = {whatsapp: "6281...", email: "...", jam_layanan: "09.00 - 21.00 WIB, setiap hari",
 * jam_buka: "09:00", jam_tutup: "21:00", balasan: "1 jam"}. Kunci yang kosong atau tidak valid tidak ditampilkan.
 */
class KontakLayanan
{
    /**
     * @return array{whatsapp:?string,email:?string,jam_layanan:?string,balasan:?string,buka:?bool,ada:bool}
     */
    public function ambil(?Carbon $sekarang = null): array
    {
        $wa = preg_replace('/\D/', '', (string) Pengaturan::ambil('kontak.whatsapp', '')) ?? '';
        $wa = preg_match('/^62\d{8,13}$/', $wa) ? $wa : null;

        $email = trim((string) Pengaturan::ambil('kontak.email', ''));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

        $jam = $this->teks('kontak.jam_layanan');
        $balasan = $this->teks('kontak.balasan');

        return [
            'whatsapp' => $wa ? 'https://wa.me/'.$wa.'?text='.rawurlencode('Halo undangyah.com, saya butuh bantuan') : null,
            'email' => $email,
            'jam_layanan' => $jam,
            'balasan' => $balasan,
            'buka' => $this->sedangBuka($sekarang ?? now()),
            'ada' => $wa !== null || $email !== null,
        ];
    }

    private function teks(string $kunci): ?string
    {
        $t = trim(strip_tags((string) Pengaturan::ambil($kunci, '')));

        return $t === '' ? null : mb_substr($t, 0, 120);
    }

    private function sedangBuka(Carbon $sekarang): ?bool
    {
        $buka = (string) Pengaturan::ambil('kontak.jam_buka', '');
        $tutup = (string) Pengaturan::ambil('kontak.jam_tutup', '');
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $buka) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $tutup) || $buka >= $tutup) {
            return null;
        }
        $kini = $sekarang->format('H:i');

        return $kini >= $buka && $kini < $tutup;
    }
}
