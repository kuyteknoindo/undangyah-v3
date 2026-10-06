<?php

namespace App\Livewire\Akun;

use App\Mail\KonfirmasiEmailBaruMail;
use App\Models\User;
use App\Services\FotoProfil;
use App\Services\RingkasanAkun;
use App\Support\NomorWa;
use App\Support\Perangkat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Livewire\Component;

class InformasiAkun extends Component
{
    public const MENIT_TAUTAN_EMAIL = 60;

    public string $nama = '';

    public string $kota = '';

    public string $bahasa = 'id';

    /** Nilai tersimpan, pembanding indikator "belum disimpan". */
    public array $asli = [];

    public string $emailBaru = '';

    public string $waBaru = '';

    public bool $tampilWa = false;

    public bool $tampilHapus = false;

    public string $teksHapus = '';

    public string $sandiHapus = '';

    public string $catatan = '';

    public function mount(): void
    {
        $this->muat();
    }

    private function muat(): void
    {
        $user = auth()->user()->fresh();
        $this->nama = $user->name;
        $this->kota = (string) $user->kota;
        $this->bahasa = $user->bahasa ?: 'id';
        $this->asli = ['nama' => $this->nama, 'kota' => $this->kota, 'bahasa' => $this->bahasa];
    }

    public function simpanData(): void
    {
        $this->nama = trim($this->nama);
        $this->kota = trim($this->kota);

        $this->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'kota' => ['nullable', 'string', 'max:100'],
            'bahasa' => ['required', Rule::in(['id'])],
        ], [
            'nama.required' => 'Nama wajib diisi.',
            'nama.min' => 'Nama minimal 3 huruf.',
        ]);

        auth()->user()->fill(['name' => $this->nama, 'kota' => $this->kota !== '' ? $this->kota : null, 'bahasa' => $this->bahasa])->save();

        $this->muat();
        $this->catatan = 'Informasi akun disimpan.';
    }

    public function kirimTautanEmail(): void
    {
        $user = auth()->user();
        $this->emailBaru = mb_strtolower(trim($this->emailBaru));

        $kunci = 'ubah-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 3)) {
            $this->addError('emailBaru', 'Terlalu banyak permintaan. Coba lagi dalam '.max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60)).' menit.');

            return;
        }

        $this->validate(['emailBaru' => ['required', 'email:rfc', 'max:255']], [
            'emailBaru.required' => 'Isi email baru.',
            'emailBaru.email' => 'Format email belum benar.',
        ]);

        if ($this->emailBaru === mb_strtolower($user->email)) {
            $this->addError('emailBaru', 'Email ini sama dengan email saat ini.');

            return;
        }
        if (User::where('email', $this->emailBaru)->where('id', '!=', $user->id)->exists()) {
            $this->addError('emailBaru', 'Email ini sudah dipakai akun lain.');

            return;
        }

        $this->kirimKonfirmasi($user, $this->emailBaru);
        $this->emailBaru = '';
    }

    public function kirimUlangTautan(): void
    {
        $user = auth()->user();
        $kunci = 'ubah-email:'.$user->id;

        if (! $user->email_baru) {
            return;
        }
        if (RateLimiter::tooManyAttempts($kunci, 3)) {
            $this->catatan = 'Terlalu banyak permintaan. Coba lagi dalam '.max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60)).' menit.';

            return;
        }

        $this->kirimKonfirmasi($user, $user->email_baru);
    }

    private function kirimKonfirmasi(User $user, string $email): void
    {
        RateLimiter::hit('ubah-email:'.$user->id, 3600);

        $user->forceFill(['email_baru' => $email, 'email_baru_dikirim_at' => now()])->save();

        $tautan = URL::temporarySignedRoute('akun.email.konfirmasi', now()->addMinutes(self::MENIT_TAUTAN_EMAIL), [
            'id' => $user->id,
            'h' => sha1(mb_strtolower($email)),
        ]);
        Mail::to($email)->send(new KonfirmasiEmailBaruMail($user->name, $email, $tautan, self::MENIT_TAUTAN_EMAIL));

        $this->catatan = 'Tautan konfirmasi dikirim ke '.$email.'. Email berubah setelah tautan dibuka.';
    }

    public function batalkanEmail(): void
    {
        auth()->user()->forceFill(['email_baru' => null, 'email_baru_dikirim_at' => null])->save();
        $this->catatan = 'Perubahan email dibatalkan.';
    }

    public function bukaWa(): void
    {
        $this->reset('waBaru');
        $this->resetErrorBag('waBaru');
        $this->tampilWa = true;
    }

    public function simpanWa(): void
    {
        $user = auth()->user();
        $inti = NomorWa::ringkas($this->waBaru);
        $this->waBaru = $inti;

        $this->validate(['waBaru' => ['required', 'regex:/^8\d{8,12}$/']], [
            'waBaru.required' => 'Nomor WhatsApp wajib diisi.',
            'waBaru.regex' => 'Nomor belum benar. Contoh: 812 3456 7890.',
        ]);

        if ($inti === NomorWa::ringkas($user->phone)) {
            $this->addError('waBaru', 'Nomor ini sama dengan nomor saat ini.');

            return;
        }

        // Verifikasi OTP nomor belum tersedia: nomor baru tersimpan dengan status "belum diverifikasi".
        $user->forceFill(['phone' => '62'.$inti, 'phone_verified_at' => null])->save();

        $this->tampilWa = false;
        $this->reset('waBaru');
        $this->catatan = 'Nomor WhatsApp diubah. Verifikasi OTP belum tersedia, jadi nomor ditandai Belum diverifikasi.';
    }

    public function bukaHapus(): void
    {
        $this->reset('teksHapus', 'sandiHapus');
        $this->resetErrorBag(['teksHapus', 'sandiHapus']);
        $this->tampilHapus = true;
    }

    public function hapusAkun(FotoProfil $foto)
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $this->addError('teksHapus', 'Akun admin tidak bisa dihapus dari sini.');

            return null;
        }

        $kunci = 'hapus-akun:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('sandiHapus', 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.');

            return null;
        }

        if (mb_strtoupper(trim($this->teksHapus)) !== 'HAPUS') {
            $this->addError('teksHapus', 'Ketik HAPUS untuk melanjutkan.');

            return null;
        }
        if ($this->sandiHapus === '' || ! Hash::check($this->sandiHapus, $user->password)) {
            RateLimiter::hit($kunci, 900);
            $this->addError('sandiHapus', 'Kata sandi salah.');

            return null;
        }

        $foto->hapus($user->foto_path);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $user->delete();

        session()->flash('toast', ['pesan' => 'Akunmu sudah dihapus.', 'tipe' => 'info']);

        return $this->redirect(route('masuk'));
    }

    /** @return array{nama:string,aktif:string,lain:int} */
    private function sesi(User $user): array
    {
        $lain = 0;
        if (config('session.driver') === 'database') {
            $lain = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', session()->getId())
                ->where('last_activity', '>', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
                ->count();
        }

        return ['nama' => Perangkat::nama(request()->userAgent()), 'ip' => (string) request()->ip(), 'lain' => $lain];
    }

    public function render(RingkasanAkun $ringkasan)
    {
        $user = auth()->user()->fresh();

        return view('livewire.akun.informasi-akun', [
            'user' => $user,
            'emailTerverifikasi' => $user->hasVerifiedEmail(),
            'telepon' => $user->teleponTampil(),
            'teleponTerverifikasi' => $user->phone && $user->phone_verified_at,
            'sesi' => $this->sesi($user),
            'infoSandi' => $ringkasan->teksSandiTerakhirDiubah($user),
        ])->layout('layouts::app', ['judul' => 'Informasi Akun', 'kecil' => 'Akun']);
    }
}
