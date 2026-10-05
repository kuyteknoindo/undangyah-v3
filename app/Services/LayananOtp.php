<?php

namespace App\Services;

use App\Mail\KodeOtpMail;
use App\Models\KodeOtp;
use App\Support\HasilOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OTP lewat email: 6 digit, disimpan sebagai HMAC (bukan teks), satu kode aktif per (email, tujuan).
 */
class LayananOtp
{
    public const TUJUAN_VERIFIKASI = 'verifikasi_email';

    public const TUJUAN_RESET = 'reset_sandi';

    public const PANJANG = 6;

    public const BERLAKU_MENIT = 10;

    public const MAKS_SALAH = 5;

    public const JEDA_KIRIM_DETIK = 60;

    public const MAKS_KIRIM_PER_JAM = 5;

    /**
     * Kirim kode baru. Mengembalikan 0 bila terkirim, atau jumlah detik tunggu bila kena batas.
     */
    public function kirim(string $email, string $tujuan, string $nama = ''): int
    {
        $email = $this->normal($email);
        $kunciJeda = "otp-jeda:{$tujuan}:{$email}";
        $kunciJam = "otp-jam:{$tujuan}:{$email}";

        if (RateLimiter::tooManyAttempts($kunciJeda, 1)) {
            return max(1, RateLimiter::availableIn($kunciJeda));
        }
        if (RateLimiter::tooManyAttempts($kunciJam, self::MAKS_KIRIM_PER_JAM)) {
            return max(1, RateLimiter::availableIn($kunciJam));
        }
        RateLimiter::hit($kunciJeda, self::JEDA_KIRIM_DETIK);
        RateLimiter::hit($kunciJam, 3600);

        $kode = str_pad((string) random_int(0, 10 ** self::PANJANG - 1), self::PANJANG, '0', STR_PAD_LEFT);

        KodeOtp::updateOrCreate(
            ['email' => $email, 'tujuan' => $tujuan],
            [
                'code_hash' => $this->hash($kode),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::BERLAKU_MENIT),
                'consumed_at' => null,
            ],
        );

        Mail::to($email)->send(new KodeOtpMail($kode, $nama, $tujuan, self::BERLAKU_MENIT));

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
            ->where('attempts', '<', self::MAKS_SALAH)->exists();
    }

    public function periksa(string $email, string $tujuan, string $kode): HasilOtp
    {
        $email = $this->normal($email);
        $kode = preg_replace('/\D/', '', $kode) ?? '';

        return DB::transaction(function () use ($email, $tujuan, $kode) {
            $baris = KodeOtp::where('email', $email)->where('tujuan', $tujuan)->lockForUpdate()->first();

            if (! $baris || $baris->consumed_at || $baris->expires_at->isPast()) {
                return HasilOtp::TidakAda;
            }
            if ($baris->attempts >= self::MAKS_SALAH) {
                return HasilOtp::Terkunci;
            }

            if (strlen($kode) === self::PANJANG && hash_equals($baris->code_hash, $this->hash($kode))) {
                $baris->update(['consumed_at' => now()]);

                return HasilOtp::Berhasil;
            }

            $baris->increment('attempts');

            return $baris->attempts >= self::MAKS_SALAH ? HasilOtp::Terkunci : HasilOtp::Salah;
        });
    }

    /** Sisa kesempatan untuk kode aktif (untuk pesan galat). */
    public function sisaPercobaan(string $email, string $tujuan): int
    {
        $baris = KodeOtp::where('email', $this->normal($email))->where('tujuan', $tujuan)->first();

        return $baris ? max(0, self::MAKS_SALAH - $baris->attempts) : 0;
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
