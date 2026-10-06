<?php

namespace App\Livewire\Akun;

use App\Services\RingkasanAkun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class UbahSandi extends Component
{
    private const BATAS_SALAH = 5;

    private const MENIT_BLOKIR = 15;

    public string $sandiLama = '';

    public string $sandiBaru = '';

    public string $sandiKonfirmasi = '';

    public string $catatan = '';

    public function ubah(): void
    {
        $user = auth()->user();
        $kunci = 'ubah-sandi:'.$user->id;

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_SALAH)) {
            $this->addError('sandiLama', 'Terlalu banyak percobaan salah. Coba lagi dalam '.max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60)).' menit.');

            return;
        }

        $this->validate([
            'sandiLama' => ['required', 'string', 'max:128'],
            'sandiBaru' => ['required', 'string', 'max:128', Password::min(8)->letters()->numbers()],
            'sandiKonfirmasi' => ['required', 'same:sandiBaru'],
        ], [
            'sandiLama.required' => 'Isi kata sandi saat ini.',
            'sandiBaru.required' => 'Isi kata sandi baru.',
            'sandiBaru.min' => 'Minimal 8 karakter dengan huruf dan angka.',
            'sandiBaru.letters' => 'Minimal 8 karakter dengan huruf dan angka.',
            'sandiBaru.numbers' => 'Minimal 8 karakter dengan huruf dan angka.',
            'sandiKonfirmasi.required' => 'Ulangi kata sandi baru.',
            'sandiKonfirmasi.same' => 'Kata sandi tidak sama.',
        ]);

        if (! Hash::check($this->sandiLama, $user->password)) {
            RateLimiter::hit($kunci, self::MENIT_BLOKIR * 60);
            $this->addError('sandiLama', 'Kata sandi saat ini salah.');

            return;
        }

        if ($this->sandiBaru === $this->sandiLama) {
            $this->addError('sandiBaru', 'Kata sandi baru harus berbeda dari yang lama.');

            return;
        }

        RateLimiter::clear($kunci);

        // Sesi baru untuk perangkat ini, lalu akhiri sesi di perangkat lain dan batalkan cookie "ingat saya".
        session()->regenerate();
        $user->forceFill([
            'password' => $this->sandiBaru,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
        }

        $this->reset('sandiLama', 'sandiBaru', 'sandiKonfirmasi');
        $this->resetErrorBag();
        $this->catatan = 'Kata sandi berhasil diubah. Sesi di perangkat lain diakhiri; pakai kata sandi baru saat masuk berikutnya.';
    }

    public function bersihkan(): void
    {
        $this->reset('sandiLama', 'sandiBaru', 'sandiKonfirmasi');
        $this->resetErrorBag();
    }

    public function render(RingkasanAkun $ringkasan)
    {
        $user = auth()->user();
        $terakhir = $user->password_changed_at
            ? $ringkasan->teksSandiTerakhirDiubah($user).' ('.$user->password_changed_at->locale('id')->translatedFormat('j M Y').'). '
            : 'Belum ada perubahan kata sandi yang tercatat. ';

        return view('livewire.akun.ubah-sandi', ['terakhir' => $terakhir.'Pakai kata sandi yang tidak dipakai di layanan lain.'])
            ->layout('layouts::app', ['judul' => 'Ubah Kata Sandi', 'kecil' => 'Akun']);
    }
}
