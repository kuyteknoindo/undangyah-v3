<?php

namespace App\Services;

use App\Models\LogAksiAdmin;
use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\Paket;
use App\Models\Pesanan;
use App\Models\User;
use App\Support\AksiAdminDitolak;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Semua perubahan admin atas akun pengguna lewat sini: aturan keselamatan (tidak mengubah diri sendiri, selalu ada admin
 * aktif) dan pencatatan ada di satu tempat. Pemanggil tetap wajib memeriksa bahwa dirinya admin.
 */
class PengelolaPengguna
{
    public function __construct(private LayananOtp $otp, private Notifikasi $notifikasi) {}

    public function ubahPeran(User $admin, User $target, string $peran): void
    {
        $this->pastikanAdmin($admin);
        if (! in_array($peran, [User::PERAN_USER, User::PERAN_ADMIN], true)) {
            throw new AksiAdminDitolak('Peran tidak dikenal.');
        }
        $this->bukanDiriSendiri($admin, $target, 'Kamu tidak bisa mengubah peranmu sendiri.');

        DB::transaction(function () use ($admin, $target, $peran) {
            $t = User::lockForUpdate()->findOrFail($target->id);
            if ($t->role === $peran) {
                throw new AksiAdminDitolak('Peran pengguna ini sudah '.$this->labelPeran($peran).'.');
            }
            if ($t->role === User::PERAN_ADMIN) {
                $this->pastikanAdaAdminLain($t);
            }
            if ($peran === User::PERAN_ADMIN && $t->ditangguhkan_at) {
                throw new AksiAdminDitolak('Aktifkan akun yang ditangguhkan sebelum menjadikannya admin.');
            }
            $lama = $t->role;
            $t->forceFill(['role' => $peran])->save();
            LogAksiAdmin::catat($admin, 'ubah_peran', $t, ['dari' => $lama, 'ke' => $peran]);
        });
    }

    /**
     * Ubah/berikan paket secara manual. Tidak membuat pesanan. `$sampai` null = tanpa batas waktu (tidak berlaku untuk gratis).
     */
    public function aturPaket(User $admin, User $target, string $kode, ?Carbon $sampai, ?string $catatan = null): void
    {
        $this->pastikanAdmin($admin);
        $kode = strtolower(trim($kode));
        if ($kode !== 'gratis' && ! Paket::where('kode', $kode)->exists()) {
            throw new AksiAdminDitolak('Paket tidak ditemukan.');
        }
        if ($kode === 'gratis') {
            $sampai = null;
        } elseif ($sampai !== null && ($sampai->isPast() || $sampai->gt(now()->addYears(10)))) {
            throw new AksiAdminDitolak('Masa berlaku harus di masa depan dan paling lama 10 tahun.');
        }

        DB::transaction(function () use ($admin, $target, $kode, $sampai, $catatan) {
            $t = User::lockForUpdate()->findOrFail($target->id);
            $sebelum = ['paket' => $t->paket, 'sampai' => $t->paket_sampai?->toIso8601String()];
            $t->forceFill(['paket' => $kode, 'paket_sampai' => $sampai])->save();
            LogAksiAdmin::catat($admin, 'atur_paket', $t, [
                'sebelum' => $sebelum,
                'sesudah' => ['paket' => $kode, 'sampai' => $sampai?->toIso8601String()],
                'catatan' => $catatan ? Str::limit(trim($catatan), 200, '') : null,
            ]);

            $nama = $kode === 'gratis' ? 'Gratis' : (Paket::where('kode', $kode)->value('nama') ?? ucfirst($kode));
            $this->notifikasi->kirim($t, ModelNotifikasi::TIPE_PEMBAYARAN, 'Paket akunmu diperbarui',
                'Paket '.$nama.($sampai ? ' aktif sampai '.$sampai->locale('id')->translatedFormat('j M Y').'.' : ($kode === 'gratis' ? ' sekarang berlaku.' : ' aktif tanpa batas waktu.')),
                '/billing');
        });
    }

    public function tandaiTerverifikasi(User $admin, User $target): void
    {
        $this->pastikanAdmin($admin);
        $t = User::findOrFail($target->id);
        if ($t->hasVerifiedEmail()) {
            throw new AksiAdminDitolak('Email pengguna ini sudah terverifikasi.');
        }
        $t->markEmailAsVerified();
        event(new Verified($t));
        LogAksiAdmin::catat($admin, 'verifikasi_manual', $t);
    }

    /** Kirim ulang kode verifikasi (OTP) ke email pengguna. Mengikuti jeda dan batas per jam OTP. */
    public function kirimUlangVerifikasi(User $admin, User $target): void
    {
        $this->pastikanAdmin($admin);
        $t = User::findOrFail($target->id);
        if ($t->hasVerifiedEmail()) {
            throw new AksiAdminDitolak('Email pengguna ini sudah terverifikasi.');
        }
        $tunggu = $this->otp->kirim($t->email, LayananOtp::TUJUAN_VERIFIKASI, $t->name);
        if ($tunggu > 0) {
            throw new AksiAdminDitolak('Kode baru baru saja dikirim. Coba lagi dalam '.$tunggu.' detik.');
        }
        LogAksiAdmin::catat($admin, 'kirim_ulang_verifikasi', $t);
    }

    public function tangguhkan(User $admin, User $target, ?string $alasan = null): void
    {
        $this->pastikanAdmin($admin);
        $this->bukanDiriSendiri($admin, $target, 'Akunmu sendiri tidak bisa ditangguhkan.');

        DB::transaction(function () use ($admin, $target, $alasan) {
            $t = User::lockForUpdate()->findOrFail($target->id);
            if ($t->ditangguhkan_at) {
                throw new AksiAdminDitolak('Akun ini sudah ditangguhkan.');
            }
            if ($t->isAdmin()) {
                $this->pastikanAdaAdminLain($t);
            }
            $alasan = $alasan !== null ? Str::limit(trim(preg_replace('/\s+/', ' ', $alasan) ?? ''), 200, '') : null;
            $t->forceFill([
                'ditangguhkan_at' => now(),
                'ditangguhkan_alasan' => $alasan ?: null,
                'remember_token' => Str::random(60), // mematikan cookie "ingat saya"
            ])->save();
            $this->putusSesi($t);
            LogAksiAdmin::catat($admin, 'tangguhkan', $t, ['alasan' => $alasan ?: null]);
        });
    }

    public function aktifkan(User $admin, User $target): void
    {
        $this->pastikanAdmin($admin);
        $t = User::findOrFail($target->id);
        if (! $t->ditangguhkan_at) {
            throw new AksiAdminDitolak('Akun ini tidak sedang ditangguhkan.');
        }
        $t->forceFill(['ditangguhkan_at' => null, 'ditangguhkan_alasan' => null])->save();
        LogAksiAdmin::catat($admin, 'aktifkan', $t);
    }

    /** Hapus akun beserta datanya (cascade). `$emailKonfirmasi` harus sama persis dengan email akun. */
    public function hapus(User $admin, User $target, string $emailKonfirmasi): void
    {
        $this->pastikanAdmin($admin);
        $this->bukanDiriSendiri($admin, $target, 'Akunmu sendiri tidak bisa dihapus.');

        DB::transaction(function () use ($admin, $target, $emailKonfirmasi) {
            $t = User::lockForUpdate()->findOrFail($target->id);
            if ($t->isAdmin()) {
                throw new AksiAdminDitolak('Turunkan peran admin ini menjadi pengguna sebelum menghapus akunnya.');
            }
            if (mb_strtolower(trim($emailKonfirmasi)) !== mb_strtolower($t->email)) {
                throw new AksiAdminDitolak('Email konfirmasi tidak cocok.');
            }

            // Berkas di disk tidak ikut terhapus oleh cascade basis data.
            Pesanan::where('user_id', $t->id)->whereNotNull('bukti_path')->pluck('bukti_path')
                ->each(fn (string $path) => Storage::disk(KonfirmasiPesanan::DISK_BUKTI)->delete($path));
            if ($t->foto_path) {
                Storage::disk('public')->delete($t->foto_path);
            }

            LogAksiAdmin::catat($admin, 'hapus_akun', $t, ['nama' => $t->name, 'paket' => $t->paket]);
            $this->putusSesi($t);
            $t->delete();
        });
    }

    /** @return array<string,mixed> Ringkasan detail satu pengguna. Tidak pernah memuat sandi/hash/token. */
    public function detail(User $user): array
    {
        $undangan = DB::table('undangan')->where('user_id', $user->id);
        $idUndangan = (clone $undangan)->pluck('id');

        return [
            'undangan' => $idUndangan->count(),
            'undangan_terbit' => (clone $undangan)->where('status', 'terbit')->count(),
            'tamu' => $idUndangan->isEmpty() ? 0 : DB::table('tamu')->whereIn('undangan_id', $idUndangan)->count(),
            'pesanan' => Pesanan::where('user_id', $user->id)->latest('id')->limit(5)->get(['id', 'nomor', 'total', 'status', 'kedaluwarsa_at', 'created_at']),
            'aksi' => LogAksiAdmin::where('target_id', $user->id)->latest('id')->limit(5)->get(),
            'paket_manual' => LogAksiAdmin::where('target_id', $user->id)->where('aksi', 'atur_paket')->latest('id')->first(),
        ];
    }

    public function labelPeran(string $peran): string
    {
        return $peran === User::PERAN_ADMIN ? 'admin' : 'pengguna';
    }

    private function pastikanAdmin(User $admin): void
    {
        abort_unless($admin->exists && $admin->isAdmin() && ! $admin->ditangguhkan_at, 403);
    }

    private function bukanDiriSendiri(User $admin, User $target, string $pesan): void
    {
        if ($admin->is($target)) {
            throw new AksiAdminDitolak($pesan);
        }
    }

    /** Pastikan masih ada admin aktif selain `$target` (baris admin terkunci agar dua aksi bersamaan tidak lolos). */
    private function pastikanAdaAdminLain(User $target): void
    {
        $lain = User::where('role', User::PERAN_ADMIN)->whereNull('ditangguhkan_at')->whereKeyNot($target->id)->lockForUpdate()->pluck('id')->count();
        if ($lain < 1) {
            throw new AksiAdminDitolak('Harus selalu ada setidaknya satu admin aktif.');
        }
    }

    private function putusSesi(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
