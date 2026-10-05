<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\LayananOtp;
use App\Support\HasilOtp;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Atur ulang kata sandi lewat OTP email: 1 email -> 2 kode -> 3 kata sandi baru -> 4 selesai.
 * Langkah dan email tujuan terkunci dari sisi klien; izin ke langkah 3 disimpan di sesi server.
 */
class LupaSandi extends Component
{
    public const SESI = 'reset_sandi_ok';

    #[Locked]
    public int $langkah = 1;

    #[Locked]
    public string $tujuan = '';

    public string $email = '';

    public string $kode = '';

    public string $sandi = '';

    public string $sandiKonfirmasi = '';

    public int $jeda = 0;

    public string $info = '';

    public function kirimKode(LayananOtp $otp): void
    {
        $this->email = mb_strtolower(trim($this->email));
        $this->validate(['email' => ['required', 'email:rfc', 'max:255']]);

        $email = $this->email;
        $kunciIp = 'lupa-ip:'.request()->ip();
        if (RateLimiter::tooManyAttempts($kunciIp, 8)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak permintaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunciIp) / 60).' menit.']);
        }
        RateLimiter::hit($kunciIp, 900);

        // Respons sama baik email terdaftar maupun tidak (tidak membocorkan keberadaan akun).
        $user = User::where('email', $email)->first();
        if ($user) {
            $otp->kirim($user->email, LayananOtp::TUJUAN_RESET, $user->name);
        }

        $this->tujuan = $email;
        $this->langkah = 2;
        $this->kode = '';
        $this->jeda = $otp->sisaJeda($email, LayananOtp::TUJUAN_RESET) ?: LayananOtp::JEDA_KIRIM_DETIK;
    }

    public function verifikasi(LayananOtp $otp): void
    {
        abort_unless($this->langkah === 2, 403);
        $this->info = '';
        $kunci = 'lupa-cek:'.$this->tujuan.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->gagal('Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunci) / 60).' menit.');
        }
        if (! preg_match('/^\d{6}$/', $this->kode)) {
            $this->gagal('Masukkan kode 6 digit.', false);
        }

        $hasil = $otp->periksa($this->tujuan, LayananOtp::TUJUAN_RESET, $this->kode);

        if ($hasil === HasilOtp::Berhasil) {
            RateLimiter::clear($kunci);
            session()->put(self::SESI, ['email' => $this->tujuan, 'sampai' => now()->addMinutes(15)->timestamp]);
            $this->langkah = 3;
            $this->kode = '';

            return;
        }

        RateLimiter::hit($kunci, 900);

        $this->gagal(match ($hasil) {
            HasilOtp::Salah => 'Kode salah. Sisa '.$otp->sisaPercobaan($this->tujuan, LayananOtp::TUJUAN_RESET).' percobaan.',
            HasilOtp::Terkunci => 'Terlalu banyak kode salah. Minta kode baru.',
            default => 'Kode salah atau sudah kedaluwarsa. Minta kode baru.',
        });
    }

    public function kirimUlang(LayananOtp $otp): void
    {
        abort_unless($this->langkah === 2, 403);

        $user = User::where('email', $this->tujuan)->first();
        $tunggu = $user ? $otp->kirim($user->email, LayananOtp::TUJUAN_RESET, $user->name) : 0;

        if ($tunggu > 0) {
            $this->jeda = $tunggu;
            $this->dispatch('kode-dikirim', detik: $tunggu);
            $this->gagal('Tunggu '.$tunggu.' detik sebelum meminta kode lagi.', false);
        }

        $this->kode = '';
        $this->jeda = LayananOtp::JEDA_KIRIM_DETIK;
        $this->info = 'Jika email terdaftar, kode baru sudah dikirim.';
        $this->resetErrorBag();
        $this->dispatch('kode-dikirim', detik: LayananOtp::JEDA_KIRIM_DETIK);
        $this->dispatch('otp-reset');
    }

    public function ubahEmail(): void
    {
        session()->forget(self::SESI);
        $this->reset('langkah', 'tujuan', 'kode', 'sandi', 'sandiKonfirmasi', 'info');
        $this->resetErrorBag();
    }

    public function simpanSandi(): void
    {
        $izin = session(self::SESI);
        if ($this->langkah !== 3 || ! is_array($izin) || ($izin['email'] ?? null) !== $this->tujuan || ($izin['sampai'] ?? 0) < now()->timestamp) {
            session()->forget(self::SESI);
            $this->reset('langkah', 'tujuan', 'kode', 'sandi', 'sandiKonfirmasi');
            throw ValidationException::withMessages(['sandi' => 'Sesi atur ulang sudah berakhir. Mulai lagi dari awal.']);
        }

        $this->validate([
            'sandi' => ['required', 'string', 'max:128', Password::min(8)->letters()->numbers()],
            'sandiKonfirmasi' => ['required', 'same:sandi'],
        ], [
            'sandiKonfirmasi.same' => 'Konfirmasi kata sandi tidak cocok.',
            'sandiKonfirmasi.required' => 'Ulangi kata sandi baru.',
        ]);

        $user = User::where('email', $this->tujuan)->first();
        if ($user) {
            $user->password = $this->sandi;
            $user->setRememberToken(Str::random(60)); // semua sesi "ingat saya" lama tidak berlaku lagi
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified(); // kepemilikan email sudah terbukti lewat OTP
            }
            $user->save();
        }

        session()->forget(self::SESI);
        $this->reset('sandi', 'sandiKonfirmasi');
        $this->langkah = 4;
    }

    private function gagal(string $pesan, bool $kosongkan = true): never
    {
        if ($kosongkan) {
            $this->kode = '';
            $this->dispatch('otp-reset');
        }

        throw ValidationException::withMessages(['kode' => $pesan]);
    }

    public function render()
    {
        return view('livewire.auth.lupa-sandi')->layout('layouts::guest', ['judul' => 'Lupa kata sandi']);
    }
}
