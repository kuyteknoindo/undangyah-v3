<?php

namespace App\Services;

use App\Models\LogAksiAdmin;
use App\Models\User;
use App\Support\KonfirmasiSandiGagal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Konfirmasi kata sandi untuk aksi admin yang sensitif (hapus pengguna, ubah rekening, backup, ekspor PII, dst.).
 *
 * Satu stempel waktu per sesi dan per admin (bukan per aksi): setelah sandi benar, SEMUA aksi sensitif terbuka selama
 * MASA_DETIK (5 menit), lalu diminta lagi. Stempel terikat ke id pengguna dan ke sidik hash sandinya, jadi otomatis
 * tidak berlaku bila sandi diganti; dihapus juga saat masuk/keluar (lihat CabutKonfirmasiSandiAdmin).
 * Percobaan salah dibatasi per admin+IP. Kata sandi tidak pernah disimpan, dicatat, atau masuk log audit.
 *
 * Penegakan ada di sisi server: setiap metode aksi sensitif memanggil `MintaKonfirmasiSandi::butuhSandi()` (Livewire)
 * atau `berlaku()` (controller) di dalam dirinya sendiri. Bila nanti ada restore ke database hidup, pasang titik
 * pengaman yang sama di awal aksinya.
 */
class KonfirmasiSandiAdmin
{
    public const KUNCI = 'konfirmasi_sandi_admin';

    public const MASA_DETIK = 300;

    public const MAKS_SALAH = 5;

    public const JENDELA_DETIK = 900;

    /** @return array{uid:int,at:int,h:string} Isi stempel sesi untuk admin ini (juga dipakai tes). */
    public function stempel(User $admin, ?int $pada = null): array
    {
        return ['uid' => (int) $admin->getKey(), 'at' => $pada ?? now()->timestamp, 'h' => $this->sidikSandi($admin)];
    }

    public function tandai(User $admin): void
    {
        session()->put(self::KUNCI, $this->stempel($admin));
    }

    public function cabut(): void
    {
        session()->forget(self::KUNCI);
    }

    /** true bila admin ini baru saja mengonfirmasi sandi dan masa berlakunya belum habis. */
    public function berlaku(User $admin): bool
    {
        return $this->status($admin) === 'berlaku';
    }

    /** 'berlaku' | 'kedaluwarsa' (pernah mengonfirmasi tetapi sudah lewat/tak sah) | 'belum' */
    public function status(User $admin): string
    {
        $s = session(self::KUNCI);
        if (! is_array($s) || ! isset($s['uid'], $s['at'], $s['h'])) {
            return 'belum';
        }
        $sah = (int) $s['uid'] === (int) $admin->getKey() && hash_equals($this->sidikSandi($admin), (string) $s['h']);
        if (! $sah) {
            $this->cabut();

            return 'belum';
        }
        $umur = now()->timestamp - (int) $s['at'];
        if ($umur < 0 || $umur >= self::MASA_DETIK) {
            // Stempel basi sengaja dibiarkan (tidak berlaku lagi) agar dialog bisa menjelaskan "kedaluwarsa"; konfirmasi baru menimpanya.
            return 'kedaluwarsa';
        }

        return 'berlaku';
    }

    public function sisaDetik(User $admin): int
    {
        $s = session(self::KUNCI);

        return $this->berlaku($admin) ? max(0, self::MASA_DETIK - (now()->timestamp - (int) $s['at'])) : 0;
    }

    /** Akun tanpa sandi (mis. dibuat tanpa sandi) atau sesi "masuk sebagai" tidak bisa mengonfirmasi. */
    public function bisaKonfirmasi(User $admin): bool
    {
        return $admin->exists && $admin->isAdmin() && ! $admin->ditangguhkan_at
            && (string) $admin->getAuthPassword() !== '' && ! app(SesiImpersonasi::class)->aktif();
    }

    /**
     * Periksa sandi. Sukses: stempel dipasang. Gagal: lempar KonfirmasiSandiGagal (pesan Indonesia) dan catat di log audit.
     *
     * @throws KonfirmasiSandiGagal
     */
    public function konfirmasi(User $admin, string $sandi, string $aksi): void
    {
        if (! $this->bisaKonfirmasi($admin)) {
            throw new KonfirmasiSandiGagal('Akun ini tidak bisa mengonfirmasi dengan kata sandi, jadi aksi sensitif tidak tersedia.');
        }

        $kunci = $this->kunciBatas($admin);
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_SALAH)) {
            $this->catatTerkunci($admin, $aksi, $kunci);

            throw new KonfirmasiSandiGagal($this->pesanTerkunci(RateLimiter::availableIn($kunci)));
        }

        if ($sandi === '') {
            throw new KonfirmasiSandiGagal('Masukkan kata sandi akunmu.');
        }

        if (! Hash::check($sandi, (string) $admin->getAuthPassword())) {
            RateLimiter::hit($kunci, self::JENDELA_DETIK);
            $sisa = max(0, self::MAKS_SALAH - RateLimiter::attempts($kunci));
            LogAksiAdmin::catat($admin, 'konfirmasi_sandi_gagal', null, ['untuk' => $aksi, 'sisa_percobaan' => $sisa]);

            throw new KonfirmasiSandiGagal($sisa > 0
                ? 'Kata sandi salah. Sisa '.$sisa.' percobaan.'
                : $this->pesanTerkunci(RateLimiter::availableIn($kunci), 'Kata sandi salah. '));
        }

        RateLimiter::clear($kunci);
        $this->tandai($admin);
        LogAksiAdmin::catat($admin, 'konfirmasi_sandi', null, ['untuk' => $aksi]);
    }

    /** Pesan untuk dialog: kedaluwarsa atau pertama kali. */
    public function pesanAwal(User $admin): string
    {
        return $this->status($admin) === 'kedaluwarsa'
            ? 'Konfirmasi sebelumnya sudah kedaluwarsa (berlaku '.(self::MASA_DETIK / 60).' menit). Masukkan kata sandi lagi untuk melanjutkan.'
            : 'Aksi ini sensitif. Masukkan kata sandi akunmu untuk melanjutkan. Konfirmasi berlaku '.(self::MASA_DETIK / 60).' menit.';
    }

    private function kunciBatas(User $admin): string
    {
        return 'konfirmasi-sandi:'.$admin->getKey().'|'.request()->ip();
    }

    private function pesanTerkunci(int $detik, string $awalan = ''): string
    {
        $menit = (int) ceil(max(1, $detik) / 60);

        return $awalan.'Terlalu banyak percobaan salah. Konfirmasi dikunci sementara, coba lagi dalam '
            .($detik <= 60 ? max(1, $detik).' detik' : $menit.' menit').'.';
    }

    /** Hanya satu baris log per masa kunci supaya tabel audit tidak dibanjiri percobaan berulang. */
    private function catatTerkunci(User $admin, string $aksi, string $kunciBatas): void
    {
        $sisa = max(1, RateLimiter::availableIn($kunciBatas));
        if (Cache::add('log-'.$kunciBatas, 1, $sisa)) {
            LogAksiAdmin::catat($admin, 'konfirmasi_sandi_terkunci', null, ['untuk' => $aksi, 'kunci_detik' => $sisa]);
        }
    }

    private function sidikSandi(User $admin): string
    {
        return hash_hmac('sha256', (string) $admin->getAuthPassword(), (string) config('app.key'));
    }
}
