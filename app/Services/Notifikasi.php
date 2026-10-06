<?php

namespace App\Services;

use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pintu tunggal notifikasi dalam aplikasi. Fase lain (RSVP baru, pembayaran, broadcast admin) cukup memanggil kirim().
 * Semua operasi baca/ubah dibatasi pada pemilik: id milik pengguna lain diabaikan diam-diam.
 */
class Notifikasi
{
    /** @param  array<string,mixed>  $data */
    public function kirim(User|int $user, string $tipe, string $judul, string $isi, ?string $tautan = null, array $data = []): ModelNotifikasi
    {
        return ModelNotifikasi::create([
            'user_id' => $this->idUser($user),
            'tipe' => ModelNotifikasi::tipeValid($tipe),
            'judul' => mb_substr($judul, 0, 160),
            'isi' => $isi,
            'tautan' => ModelNotifikasi::tautanAman($tautan),
            'data' => $data ?: null,
        ]);
    }

    public function jumlahBelumDibaca(User|int $user): int
    {
        return ModelNotifikasi::query()->where('user_id', $this->idUser($user))->tampil()->belumDibaca()->count();
    }

    /** @param  int|array<int,int>  $id  @return int jumlah baris berubah */
    public function tandaiDibaca(User|int $user, int|array $id): int
    {
        return $this->milik($user, $id)->whereNull('dibaca_at')->update(['dibaca_at' => now()]);
    }

    /** @param  int|array<int,int>  $id */
    public function tandaiBelumDibaca(User|int $user, int|array $id): int
    {
        return $this->milik($user, $id)->whereNotNull('dibaca_at')->update(['dibaca_at' => null]);
    }

    public function tandaiSemuaDibaca(User|int $user): int
    {
        return ModelNotifikasi::query()->where('user_id', $this->idUser($user))->tampil()->belumDibaca()->update(['dibaca_at' => now()]);
    }

    /** @param  int|array<int,int>  $id */
    public function sembunyikan(User|int $user, int|array $id): int
    {
        return $this->milik($user, $id)->tampil()->update(['disembunyikan_at' => now()]);
    }

    /** Sembunyikan semua yang sudah dibaca; mengembalikan id-nya agar bisa diurungkan. @return Collection<int,int> */
    public function sembunyikanYangDibaca(User|int $user): Collection
    {
        $q = ModelNotifikasi::query()->where('user_id', $this->idUser($user))->tampil()->whereNotNull('dibaca_at');
        $ids = (clone $q)->pluck('id');
        if ($ids->isNotEmpty()) {
            ModelNotifikasi::query()->whereIn('id', $ids)->update(['disembunyikan_at' => now()]);
        }

        return $ids;
    }

    /** Urungkan sembunyikan. @param  int|array<int,int>  $id */
    public function pulihkan(User|int $user, int|array $id): int
    {
        return $this->milik($user, $id)->whereNotNull('disembunyikan_at')->update(['disembunyikan_at' => null]);
    }

    private function idUser(User|int $user): int
    {
        return $user instanceof User ? (int) $user->getKey() : $user;
    }

    /** @param  int|array<int,int>  $id */
    private function milik(User|int $user, int|array $id)
    {
        return ModelNotifikasi::query()
            ->where('user_id', $this->idUser($user))
            ->whereIn('id', array_map('intval', (array) $id));
    }
}
