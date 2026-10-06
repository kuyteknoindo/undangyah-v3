<?php

namespace App\Services;

use App\Mail\KodeOtpMail;
use App\Models\KodeOtp;
use App\Models\Pengaturan;
use App\Support\HasilOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OTP lewat email, disimpan sebagai HMAC (bukan teks), satu kode aktif per (email, tujuan).
 * Aturan (panjang, masa berlaku, dsb.) dibaca dari Pengaturan `otp` (diatur di Panel Admin > OTP Setting);
 * konstanta di bawah adalah nilai bawaan bila belum diatur.
 */
class LayananOtp
{
    public const TUJUAN_VERIFIKASI = 'verifikasi_email';

    public const TUJUAN_RESET = 'reset_sandi';

    public const TUJUAN_UJI = 'uji_otp';

    public const PANJANG = 6;

    public const BERLAKU_MENIT = 10;

    public const MAKS_SALAH = 5;

    public const JEDA_KIRIM_DETIK = 60;

    public const MAKS_KIRIM_PER_JAM = 5;

    /** Kunci pengaturan => [bawaan, min, maks]. */
    public const ATURAN = [
        'panjang' => [self::PANJANG, 4, 8],
        'berlaku_menit' => [self::BERLAKU_MENIT, 1, 30],
        'maks_salah' => [self::MAKS_SALAH, 1, 10],
        'jeda_detik' => [self::JEDA_KIRIM_DETIK, 10, 300],
        'maks_per_jam' => [self::MAKS_KIRIM_PER_JAM, 1, 30],
    ];

    /** @return array{panjang:int,berlaku_menit:int,maks_salah:int,jeda_detik:int,maks_per_jam:int} Nilai tak sah jatuh ke bawaan; di luar rentang dipotong. */
    public function aturan(): array
    {
        $tersimpan = Pengaturan::ambil('otp', []);
        $tersimpan = is_array($tersimpan) ? $tersimpan : [];
        $hasil = [];
        foreach (self::ATURAN as $kunci => [$bawaan, $min, $maks]) {
            $nilai = $tersimpan[$kunci] ?? $bawaan;
            $hasil[$kunci] = is_int($nilai) || (is_string($nilai) && ctype_digit($nilai)) ? max($min, min($maks, (int) $nilai)) : $bawaan;
        }

        return $hasil;
    }

    public function panjang(): int
    {
        return $this->aturan()['panjang'];
    }

    public function jedaDetik(): int
    {
        return $this->aturan()['jeda_detik'];
    }

    /**
     * Kirim kode baru. Mengembalikan 0 bila terkirim, atau jumlah detik tunggu bila kena batas.
     */
    public function kirim(string $email, string $tujuan, string $nama = ''): int
    {
        $email = $this->normal($email);
        $aturan = $this->aturan();
        $kunciJeda = "otp-jeda:{$tujuan}:{$email}";
        $kunciJam = "otp-jam:{$tujuan}:{$email}";

        if (RateLimiter::tooManyAttempts($kunciJeda, 1)) {
            return max(1, RateLimiter::availableIn($kunciJeda));
        }
        if (RateLimiter::tooManyAttempts($kunciJam, $aturan['maks_per_jam'])) {
            return max(1, RateLimiter::availableIn($kunciJam));
        }
        RateLimiter::hit($kunciJeda, $aturan['jeda_detik']);
        RateLimiter::hit($kunciJam, 3600);

        $kode = str_pad((string) random_int(0, 10 ** $aturan['panjang'] - 1), $aturan['panjang'], '0', STR_PAD_LEFT);

        KodeOtp::updateOrCreate(
            ['email' => $email, 'tujuan' => $tujuan],
            [
                'code_hash' => $this->hash($kode),
                'attempts' => 0,
                'expires_at' => now()->addMinutes($aturan['berlaku_menit']),
                'consumed_at' => null,
            ],
        );

        Mail::to($email)->send(new KodeOtpMail($kode, $nama, $tujuan, $aturan['berlaku_menit']));

        return 0;
    }

    /** Sisa detik sebelum kode boleh dikirim ulang (0 bila boleh sekarang). */
    public function sisaJeda(string $email, string $tujuan): int
    {
        return RateLimiter::availableIn('otp-jeda:'.$tujuan.':'.$this->normal($email));
    }

    public function adaKodeAktif(string $email, string $tujuan): bool
    {
        return KodeOtp::where('email', $this->normal($email))->where('tujuan', $tujuan)
            ->whereNull('consumed_at')->where('expires_at', '>', now())
            ->where('attempts', '<', $this->aturan()['maks_salah'])->exists();
    }

    public function periksa(string $email, string $tujuan, string $kode): HasilOtp
    {
        $email = $this->normal($email);
        $kode = preg_replace('/\D/', '', $kode) ?? '';
        $maksSalah = $this->aturan()['maks_salah'];

        return DB::transaction(function () use ($email, $tujuan, $kode, $maksSalah) {
            $baris = KodeOtp::where('email', $email)->where('tujuan', $tujuan)->lockForUpdate()->first();

            if (! $baris || $baris->consumed_at || $baris->expires_at->isPast()) {
                return HasilOtp::TidakAda;
            }
            if ($baris->attempts >= $maksSalah) {
                return HasilOtp::Terkunci;
            }

            // Panjang mengikuti kode yang sudah terkirim (aturan bisa berubah di antara kirim dan periksa).
            if (strlen($kode) >= self::ATURAN['panjang'][1] && strlen($kode) <= self::ATURAN['panjang'][2] && hash_equals($baris->code_hash, $this->hash($kode))) {
                $baris->update(['consumed_at' => now()]);

                return HasilOtp::Berhasil;
            }

            $baris->increment('attempts');

            return $baris->attempts >= $maksSalah ? HasilOtp::Terkunci : HasilOtp::Salah;
        });
    }

    /** Sisa kesempatan untuk kode aktif (untuk pesan galat). */
    public function sisaPercobaan(string $email, string $tujuan): int
    {
        $baris = KodeOtp::where('email', $this->normal($email))->where('tujuan', $tujuan)->first();

        return $baris ? max(0, $this->aturan()['maks_salah'] - $baris->attempts) : 0;
    }

    private function hash(string $kode): string
    {
        return hash_hmac('sha256', $kode, (string) config('app.key'));
    }

    private function normal(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
