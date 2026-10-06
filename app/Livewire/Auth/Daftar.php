<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\LayananOtp;
use App\Services\SesiPengguna;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Daftar extends Component
{
    public string $nama = '';

    public string $email = '';

    public string $whatsapp = '';

    public string $sandi = '';

    public string $sandiKonfirmasi = '';

    public bool $setuju = false;

    public function daftar(LayananOtp $otp): void
    {
        $ip = (string) request()->ip();
        $kunciIp = 'daftar-ip:'.$ip;
        if (RateLimiter::tooManyAttempts($kunciIp, 10)) {
            throw ValidationException::withMessages([
                'form' => 'Terlalu banyak percobaan daftar. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunciIp) / 60).' menit.',
            ]);
        }

        $this->email = mb_strtolower(trim($this->email));
        $this->whatsapp = $this->normalkanWa($this->whatsapp);

        $this->validate([
            'nama' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'whatsapp' => ['nullable', 'regex:/^8\d{8,12}$/'],
            'sandi' => ['required', 'string', 'max:128', Password::min(8)->letters()->numbers()],
            'sandiKonfirmasi' => ['required', 'same:sandi'],
            'setuju' => ['accepted'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp belum benar. Contoh: 81234567890.',
            'sandiKonfirmasi.same' => 'Konfirmasi kata sandi tidak cocok.',
            'sandiKonfirmasi.required' => 'Konfirmasi kata sandi wajib diisi.',
            'setuju.accepted' => 'Kamu perlu menyetujui syarat dan kebijakan privasi.',
        ]);

        RateLimiter::hit($kunciIp, 3600);

        $user = User::where('email', $this->email)->first();

        if ($user?->hasVerifiedEmail()) {
            $this->addError('email', 'Email ini sudah terdaftar. Silakan masuk.');

            return;
        }

        $data = ['name' => trim($this->nama), 'phone' => $this->whatsapp ? '62'.$this->whatsapp : null, 'password' => $this->sandi];

        if ($user) {
            // Akun lama yang belum pernah terverifikasi diambil alih pendaftar baru dengan email yang sama.
            $user->fill($data)->setRememberToken(null);
            $user->save();
            // Penyerang yang lebih dulu mendaftar dengan email ini masih memegang sesi masuk; cabut agar tak ikut terbawa setelah verifikasi.
            SesiPengguna::cabutSemua($user->id);
        } else {
            $user = User::create($data + ['email' => $this->email]);
        }

        Auth::login($user);
        session()->regenerate();
        $otp->kirim($user->email, LayananOtp::TUJUAN_VERIFIKASI, $user->name);

        $this->redirect(route('verification.notice'));
    }

    /** Terima 08xx, 8xx, +62 8xx atau 62 8xx; simpan sebagai 8xx tanpa awalan. */
    private function normalkanWa(string $nomor): string
    {
        $angka = preg_replace('/\D/', '', $nomor) ?? '';

        return preg_replace('/^(62|0)/', '', $angka) ?? '';
    }

    public function render()
    {
        return view('livewire.auth.daftar')->layout('layouts::guest', ['judul' => 'Daftar']);
    }
}
