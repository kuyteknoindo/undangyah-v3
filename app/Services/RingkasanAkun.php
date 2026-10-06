<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Data ringkas untuk halaman Profil. Tabel undangan/tamu (Fase 2) dan paket (Fase 6) belum dipakai di sini:
 * tiap metode mengembalikan nilai kosong yang aman, dan nanti cukup isi metodenya tanpa menyentuh halaman.
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
        // Fase 6: baca paket dan masa berlaku pengguna. Sementara semua akun Gratis.
        return ['kode' => 'gratis', 'nama' => 'Gratis', 'aktif' => false, 'sampai' => null, 'berakhir' => false, 'sisaHari' => null];
    }

    /** Tautan undangan yang sedang dipakai (untuk subjudul menu Link Kustom); null bila belum ada. */
    public function tautanUndangan(User $user): ?string
    {
        return null;
    }

    public function jumlahTemplatePesan(User $user): int
    {
        return 0;
    }

    public function jumlahPesanTerkirim(User $user): int
    {
        return 0;
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
