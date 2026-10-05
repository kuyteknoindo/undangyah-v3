<?php

namespace App\Livewire\Auth;

use App\Services\LayananOtp;
use App\Support\HasilOtp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class VerifikasiEmail extends Component
{
    public string $kode = '';

    public int $jeda = 0;

    public string $info = '';

    public function mount(LayananOtp $otp)
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            return $this->redirect(route('beranda'), navigate: false);
        }

        if (! $otp->adaKodeAktif($user->email, LayananOtp::TUJUAN_VERIFIKASI)) {
            $otp->kirim($user->email, LayananOtp::TUJUAN_VERIFIKASI, $user->name);
        }
        $this->jeda = $otp->sisaJeda($user->email, LayananOtp::TUJUAN_VERIFIKASI);
    }

    public function verifikasi(LayananOtp $otp): void
    {
        $user = auth()->user();
        $this->info = '';
        $kunci = 'otp-cek:'.$user->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->gagal('Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunci) / 60).' menit.');
        }

        if (! preg_match('/^\d{6}$/', $this->kode)) {
            $this->gagal('Masukkan kode 6 digit.', false);
        }

        $hasil = $otp->periksa($user->email, LayananOtp::TUJUAN_VERIFIKASI, $this->kode);

        if ($hasil === HasilOtp::Berhasil) {
            RateLimiter::clear($kunci);
            $user->markEmailAsVerified();
            event(new Verified($user));
            session()->flash('sukses', "Email terverifikasi. Selamat bergabung, {$user->namaDepan()}!");

            $this->redirect(route('beranda'), navigate: false);

            return;
        }

        RateLimiter::hit($kunci, 900);

        $this->gagal(match ($hasil) {
            HasilOtp::Salah => 'Kode salah. Sisa '.$otp->sisaPercobaan($user->email, LayananOtp::TUJUAN_VERIFIKASI).' percobaan.',
            HasilOtp::Terkunci => 'Terlalu banyak kode salah. Minta kode baru.',
            default => 'Kode sudah kedaluwarsa atau tidak ditemukan. Minta kode baru.',
        });
    }

    public function kirimUlang(LayananOtp $otp): void
    {
        $user = auth()->user();
        $tunggu = $otp->kirim($user->email, LayananOtp::TUJUAN_VERIFIKASI, $user->name);

        if ($tunggu > 0) {
            $this->jeda = $tunggu;
            $this->dispatch('kode-dikirim', detik: $tunggu);
            $this->gagal('Tunggu '.$tunggu.' detik sebelum meminta kode lagi.', false);
        }

        $this->kode = '';
        $this->jeda = LayananOtp::JEDA_KIRIM_DETIK;
        $this->info = 'Kode baru sudah dikirim ke email kamu.';
        $this->resetErrorBag();
        $this->dispatch('kode-dikirim', detik: LayananOtp::JEDA_KIRIM_DETIK);
        $this->dispatch('otp-reset');
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
        $email = auth()->user()->email;

        return view('livewire.auth.verifikasi-email', ['emailTersamar' => $this->samarkan($email)])
            ->layout('layouts::guest', ['judul' => 'Verifikasi email']);
    }

    private function samarkan(string $email): string
    {
        [$nama, $domain] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($nama, 0, 2).str_repeat('•', max(2, mb_strlen($nama) - 2)).'@'.$domain;
    }
}
