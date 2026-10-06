<?php

namespace App\Livewire\Admin;

use App\Models\KodeOtp;
use App\Models\LogAksiAdmin;
use App\Models\Pengaturan;
use App\Services\LayananOtp;
use App\Support\HasilOtp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Panel Admin > OTP Setting: aturan kode OTP email (dibaca LayananOtp), uji kirim ke email admin, dan statistik ringkas. */
class OtpSetting extends Component
{
    public string $panjang = '';

    public string $berlakuMenit = '';

    public string $maksSalah = '';

    public string $jedaDetik = '';

    public string $maksPerJam = '';

    public bool $tampilReset = false;

    public string $kodeUji = '';

    public string $hasilUji = '';

    public string $nadaUji = 'info';

    public function mount(LayananOtp $otp): void
    {
        $this->admin();
        $this->muatDariTersimpan($otp);
    }

    public function updated(string $nama): void
    {
        if ($nama !== 'kodeUji') {
            $this->resetValidation($nama);
        }
    }

    public function simpan(LayananOtp $otp): void
    {
        $admin = $this->admin();
        $nilai = $this->validasi();
        $sebelum = $otp->aturan();

        Pengaturan::simpan('otp', $nilai + ['diubah_oleh' => $admin->id, 'diubah_at' => now()->toIso8601String()]);
        LogAksiAdmin::catat($admin, 'atur_otp', null, ['sebelum' => $sebelum, 'sesudah' => $nilai]);
        $this->muatDariTersimpan($otp);
        $this->dispatch('toast', pesan: 'Pengaturan OTP disimpan dan langsung berlaku.', tipe: 'sukses');
    }

    public function batalkan(LayananOtp $otp): void
    {
        $this->admin();
        $this->muatDariTersimpan($otp);
        $this->resetValidation();
        $this->dispatch('toast', pesan: 'Perubahan dibatalkan.', tipe: 'info');
    }

    public function pilihPanjang(int $n): void
    {
        $this->panjang = (string) max(LayananOtp::ATURAN['panjang'][1], min(LayananOtp::ATURAN['panjang'][2], $n));
        $this->resetValidation('panjang');
    }

    public function bukaReset(): void
    {
        $this->admin();
        $this->tampilReset = true;
    }

    public function resetBawaan(LayananOtp $otp): void
    {
        $admin = $this->admin();
        $sebelum = $otp->aturan();
        $bawaan = collect(LayananOtp::ATURAN)->map(fn ($a) => $a[0])->all();

        Pengaturan::simpan('otp', $bawaan + ['diubah_oleh' => $admin->id, 'diubah_at' => now()->toIso8601String()]);
        LogAksiAdmin::catat($admin, 'atur_otp', null, ['sebelum' => $sebelum, 'sesudah' => $bawaan, 'reset' => true]);
        $this->tampilReset = false;
        $this->muatDariTersimpan($otp);
        $this->resetValidation();
        $this->dispatch('toast', pesan: 'Pengaturan OTP dikembalikan ke bawaan.', tipe: 'sukses');
    }

    /** Kirim kode uji ke email admin sendiri, memakai aturan yang TERSIMPAN (bukan isian yang belum disimpan). */
    public function kirimUji(LayananOtp $otp): void
    {
        $admin = $this->admin();
        $kunci = 'otp-uji:'.$admin->id;
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->setHasil('Terlalu banyak uji kirim. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunci) / 60).' menit.', 'galat');

            return;
        }
        RateLimiter::hit($kunci, 600);

        $tunggu = $otp->kirim($admin->email, LayananOtp::TUJUAN_UJI, $admin->name);
        $this->kodeUji = '';
        if ($tunggu > 0) {
            $this->setHasil('Kode uji baru saja dikirim. Tunggu '.$tunggu.' detik sebelum mengirim lagi.', 'info');

            return;
        }
        $this->setHasil('Kode uji dikirim ke '.$admin->email.'. Masukkan kodenya di bawah untuk menguji verifikasi.', 'sukses');
    }

    public function periksaUji(LayananOtp $otp): void
    {
        $admin = $this->admin();
        $kunci = 'otp-uji-cek:'.$admin->id;
        if (RateLimiter::tooManyAttempts($kunci, 15)) {
            $this->setHasil('Terlalu banyak percobaan. Coba lagi nanti.', 'galat');

            return;
        }
        RateLimiter::hit($kunci, 600);

        $this->kodeUji = preg_replace('/\D/', '', $this->kodeUji) ?? '';
        if ($this->kodeUji === '') {
            $this->setHasil('Masukkan kode dari email dulu.', 'galat');

            return;
        }

        $this->setHasilOtp($otp->periksa($admin->email, LayananOtp::TUJUAN_UJI, $this->kodeUji), $otp, $admin->email);
        $this->kodeUji = '';
    }

    private function setHasilOtp(HasilOtp $hasil, LayananOtp $otp, string $email): void
    {
        match ($hasil) {
            HasilOtp::Berhasil => $this->setHasil('Kode benar. Alur verifikasi bekerja dengan aturan ini.', 'sukses'),
            HasilOtp::Salah => $this->setHasil('Kode salah. Sisa '.$otp->sisaPercobaan($email, LayananOtp::TUJUAN_UJI).' percobaan.', 'galat'),
            HasilOtp::Terkunci => $this->setHasil('Terlalu banyak kode salah, kode terkunci. Kirim kode uji baru.', 'galat'),
            default => $this->setHasil('Kode sudah kedaluwarsa atau tidak ditemukan. Kirim kode uji baru.', 'galat'),
        };
    }

    private function setHasil(string $pesan, string $nada): void
    {
        $this->hasilUji = $pesan;
        $this->nadaUji = $nada;
    }

    private function admin()
    {
        $admin = auth()->user();
        abort_unless($admin?->isAdmin() && ! $admin->ditangguhkan_at, 403);

        return $admin;
    }

    private function muatDariTersimpan(LayananOtp $otp): void
    {
        $a = $otp->aturan();
        $this->panjang = (string) $a['panjang'];
        $this->berlakuMenit = (string) $a['berlaku_menit'];
        $this->maksSalah = (string) $a['maks_salah'];
        $this->jedaDetik = (string) $a['jeda_detik'];
        $this->maksPerJam = (string) $a['maks_per_jam'];
    }

    /** @return array{panjang:int,berlaku_menit:int,maks_salah:int,jeda_detik:int,maks_per_jam:int} */
    private function validasi(): array
    {
        $r = LayananOtp::ATURAN;
        $aturan = fn (string $k) => ['required', 'regex:/^\d{1,4}$/', 'integer', 'between:'.$r[$k][1].','.$r[$k][2]];
        $pesan = fn (string $nama, string $k, string $satuan) => [
            $nama.'.required' => 'Wajib diisi.',
            $nama.'.regex' => 'Isi dengan bilangan bulat.',
            $nama.'.integer' => 'Isi dengan bilangan bulat.',
            $nama.'.between' => 'Harus '.$r[$k][1].'-'.$r[$k][2].' '.$satuan.'.',
        ];

        $data = $this->validate([
            'panjang' => $aturan('panjang'),
            'berlakuMenit' => $aturan('berlaku_menit'),
            'maksSalah' => $aturan('maks_salah'),
            'jedaDetik' => $aturan('jeda_detik'),
            'maksPerJam' => $aturan('maks_per_jam'),
        ], $pesan('panjang', 'panjang', 'digit') + $pesan('berlakuMenit', 'berlaku_menit', 'menit') + $pesan('maksSalah', 'maks_salah', 'kali')
            + $pesan('jedaDetik', 'jeda_detik', 'detik') + $pesan('maksPerJam', 'maks_per_jam', 'permintaan per jam'));

        if ((int) $data['jedaDetik'] >= (int) $data['berlakuMenit'] * 60) {
            throw ValidationException::withMessages(['jedaDetik' => 'Jeda kirim ulang harus lebih singkat dari masa berlaku ('.((int) $data['berlakuMenit'] * 60).' detik).']);
        }

        return [
            'panjang' => (int) $data['panjang'],
            'berlaku_menit' => (int) $data['berlakuMenit'],
            'maks_salah' => (int) $data['maksSalah'],
            'jeda_detik' => (int) $data['jedaDetik'],
            'maks_per_jam' => (int) $data['maksPerJam'],
        ];
    }

    /** @return array<string,int> Hitungan dari baris otp_codes yang masih tersimpan (kode tidak pernah dibaca atau ditampilkan). */
    private function statistik(LayananOtp $otp): array
    {
        $maks = $otp->aturan()['maks_salah'];
        $dasar = fn () => KodeOtp::query()->where('tujuan', '!=', LayananOtp::TUJUAN_UJI);

        return [
            'total' => $dasar()->count(),
            'aktif' => $dasar()->whereNull('consumed_at')->where('expires_at', '>', now())->where('attempts', '<', $maks)->count(),
            'terpakai' => $dasar()->whereNotNull('consumed_at')->count(),
            'kedaluwarsa' => $dasar()->whereNull('consumed_at')->where('expires_at', '<=', now())->count(),
            'terkunci' => $dasar()->whereNull('consumed_at')->where('expires_at', '>', now())->where('attempts', '>=', $maks)->count(),
            'dibuat24' => $dasar()->where('updated_at', '>=', now()->subDay())->count(),
            'verifikasi' => $dasar()->where('tujuan', LayananOtp::TUJUAN_VERIFIKASI)->count(),
            'reset' => $dasar()->where('tujuan', LayananOtp::TUJUAN_RESET)->count(),
            'salah' => (int) $dasar()->where('attempts', '>', 0)->count(),
        ];
    }

    public function render(LayananOtp $otp)
    {
        $this->admin();
        $tersimpan = $otp->aturan();
        $diubah = Pengaturan::ambil('otp.diubah_at');
        $form = [
            'panjang' => $this->panjang, 'berlaku_menit' => $this->berlakuMenit, 'maks_salah' => $this->maksSalah,
            'jeda_detik' => $this->jedaDetik, 'maks_per_jam' => $this->maksPerJam,
        ];

        return view('livewire.admin.otp.index', [
            'tersimpan' => $tersimpan,
            'kotor' => collect($form)->map(fn ($v) => (string) $v)->all() !== collect($tersimpan)->map(fn ($v) => (string) $v)->all(),
            'bawaan' => collect(LayananOtp::ATURAN)->map(fn ($a) => $a[0])->all(),
            'rentang' => LayananOtp::ATURAN,
            'diubahAt' => is_string($diubah) ? Carbon::parse($diubah) : null,
            'adaTersimpan' => Pengaturan::ambil('otp') !== null,
            'stat' => $this->statistik($otp),
            'emailAdmin' => auth()->user()->email,
            'sisaJedaUji' => $otp->sisaJeda(auth()->user()->email, LayananOtp::TUJUAN_UJI),
            'adaKodeUji' => $otp->adaKodeAktif(auth()->user()->email, LayananOtp::TUJUAN_UJI),
            'contoh' => substr('48291375', 0, $tersimpan['panjang']),
        ])->layout('layouts::app', ['judul' => 'OTP Setting', 'sub' => 'Aturan kode verifikasi untuk daftar akun dan atur ulang sandi.']);
    }
}
