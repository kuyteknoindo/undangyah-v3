<?php

namespace App\Services;

use App\Models\Paket;
use App\Models\RiwayatKirim;
use App\Models\TemplatePesan;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Data ringkas untuk halaman Profil. Paket dibaca dari kolom langganan di `users` (Fase 6).
 */
class RingkasanAkun
{
    /** @return array{undangan:int,tamu:int,hadir:int} */
    public function statistik(User $user): array
    {
        // Fase 2: hitung dari tabel undangan (milik $user), tamu, dan RSVP berstatus hadir.
        return ['undangan' => 0, 'tamu' => 0, 'hadir' => 0];
    }

    /** @return array{kode:string,nama:string,aktif:bool,sampai:?Carbon,berakhir:bool,sisaHari:?int} */
    public function paket(User $user): array
    {
        $kode = strtolower((string) ($user->paket ?: 'gratis'));
        if ($kode === 'gratis') {
            return ['kode' => 'gratis', 'nama' => 'Gratis', 'aktif' => false, 'sampai' => null, 'berakhir' => false, 'sisaHari' => null];
        }

        $nama = Paket::where('kode', $kode)->value('nama') ?? ucfirst($kode);
        $sampai = $user->paket_sampai;
        $berakhir = $sampai !== null && $sampai->isPast();

        return [
            'kode' => $kode,
            'nama' => $nama,
            'aktif' => ! $berakhir,
            'sampai' => $sampai,
            'berakhir' => $berakhir,
            'sisaHari' => $sampai && ! $berakhir ? (int) ceil(now()->diffInSeconds($sampai, false) / 86400) : null,
        ];
    }

    /** Tautan undangan yang sedang dipakai (untuk subjudul menu Link Kustom); null bila belum ada. */
    public function tautanUndangan(User $user): ?string
    {
        $slug = Undangan::where('user_id', $user->id)
            ->where('status', 'terbit')
            ->whereNotNull('slug')
            ->latest('diterbitkan_at')
            ->value('slug');

        return $slug ? parse_url(url('/'), PHP_URL_HOST).'/'.$slug : null;
    }

    public function jumlahTemplatePesan(User $user): int
    {
        return TemplatePesan::where('user_id', $user->id)->count();
    }

    public function jumlahPesanTerkirim(User $user): int
    {
        return RiwayatKirim::where('user_id', $user->id)->count();
    }

    public function notifikasiBelumDibaca(User $user): int
    {
        if (! class_exists(Notifikasi::class)) {
            return 0;
        }

        return (int) app(Notifikasi::class)->jumlahBelumDibaca($user);
    }

    /** "Terakhir diubah 3 hari lalu" untuk kartu menu dan halaman Ubah Sandi. */
    public function teksSandiTerakhirDiubah(User $user): string
    {
        return $user->password_changed_at
            ? 'Terakhir diubah '.$user->password_changed_at->locale('id')->diffForHumans()
            : 'Belum ada perubahan tercatat';
    }
}
