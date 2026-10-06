<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Masuk extends Component
{
    public string $email = '';

    public string $sandi = '';

    public bool $ingat = true;

    public string $info = '';

    private const MAKS_PER_AKUN = 5;

    private const MAKS_PER_IP = 20;

    private const KUNCI_DETIK = 300;

    public function masuk(): void
    {
        $this->info = '';
        $this->email = mb_strtolower(trim($this->email));
        $this->validate(
            ['email' => ['required', 'email:rfc'], 'sandi' => ['required']],
            ['sandi.required' => 'Kata sandi wajib diisi.'],
        );

        $email = $this->email;
        $ip = (string) request()->ip();
        $kunciAkun = 'masuk:'.$email.'|'.$ip;
        $kunciIp = 'masuk-ip:'.$ip;

        if (RateLimiter::tooManyAttempts($kunciAkun, self::MAKS_PER_AKUN) || RateLimiter::tooManyAttempts($kunciIp, self::MAKS_PER_IP)) {
            $detik = max(RateLimiter::availableIn($kunciAkun), RateLimiter::availableIn($kunciIp));

            throw ValidationException::withMessages([
                'form' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.$this->formatWaktu($detik).'.',
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $this->sandi], $this->ingat)) {
            RateLimiter::hit($kunciAkun, self::KUNCI_DETIK);
            RateLimiter::hit($kunciIp, self::KUNCI_DETIK);
            $this->sandi = '';

            throw ValidationException::withMessages(['form' => 'Email atau kata sandi salah.']);
        }

        // Sandi benar tetapi akun ditangguhkan admin: jangan buka sesi (PengamanAkun juga menjaga sesi yang sudah ada).
        if (Auth::user()?->ditangguhkan_at) {
            Auth::logout();
            $this->sandi = '';

            throw ValidationException::withMessages(['form' => 'Akun ini ditangguhkan. Hubungi dukungan bila kamu merasa ini keliru.']);
        }

        RateLimiter::clear($kunciAkun);
        session()->regenerate();

        $this->redirectIntended(route('beranda'));
    }

    public function segera(string $penyedia): void
    {
        $nama = $penyedia === 'wa' ? 'WhatsApp' : 'Google';
        $this->info = "Masuk dengan {$nama} segera hadir. Untuk sekarang, masuk dengan email dan kata sandi.";
    }

    private function formatWaktu(int $detik): string
    {
        return $detik >= 60 ? ceil($detik / 60).' menit' : $detik.' detik';
    }

    public function render()
    {
        return view('livewire.auth.masuk')->layout('layouts::guest', ['judul' => 'Masuk']);
    }
}
