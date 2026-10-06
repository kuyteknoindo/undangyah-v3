<?php

namespace App\Services;

use App\Models\SlugDitahan;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Slug lama ditahan 90 hari setelah diganti atau undangannya dihapus: hanya pemilik lama yang boleh memakainya lagi.
 * Pesan galat tidak pernah menyebut siapa pemiliknya.
 */
class PenahananSlug
{
    public const HARI = 90;

    public function aktif(string $slug): ?SlugDitahan
    {
        return SlugDitahan::query()->where('slug', $slug)->aktif()->first();
    }

    /** Pesan galat bila slug ditahan pengguna lain, atau null bila bebas / ditahan atas nama `$penggunaId` sendiri. */
    public function galat(string $slug, ?int $penggunaId): ?string
    {
        $h = $this->aktif($slug);
        if (! $h || ($penggunaId !== null && (int) $h->user_id === $penggunaId)) {
            return null;
        }

        return 'Alamat ini sedang ditahan sementara dan baru bisa dipakai lagi mulai '.$h->ditahan_sampai->locale('id')->translatedFormat('j F Y').'. Pilih alamat lain.';
    }

    /** Dari daftar calon: slug yang ditahan orang lain. @return list<string> */
    public function ditahanLain(array $calon, ?int $penggunaId): array
    {
        if ($calon === []) {
            return [];
        }

        return SlugDitahan::query()->whereIn('slug', $calon)->aktif()
            ->when($penggunaId !== null, fn ($q) => $q->where('user_id', '!=', $penggunaId))
            ->pluck('slug')->all();
    }

    /** Lempar galat validasi bila slug ditahan orang lain (dipanggil di dalam transaksi, sesudah menulis, agar permintaan serentak tidak lolos). */
    public function pastikan(string $slug, ?int $penggunaId): void
    {
        if ($pesan = $this->galat($slug, $penggunaId)) {
            throw ValidationException::withMessages(['slug' => $pesan]);
        }
    }

    /** Tahan slug 90 hari dari sekarang; slug yang sama ditahan lagi memperbarui barisnya (tidak menggandakan). */
    public function tahan(string $slug, int $penggunaId, ?int $undanganId, string $alasan): void
    {
        $nilai = [
            'user_id' => $penggunaId,
            'undangan_id' => $undanganId,
            'ditahan_sampai' => now()->addDays(self::HARI),
            'alasan' => $alasan,
        ];
        $baris = SlugDitahan::query()->where('slug', $slug)->first();
        if ($baris) {
            $baris->forceFill($nilai)->save();

            return;
        }
        SlugDitahan::query()->create(['slug' => $slug] + $nilai);
    }

    /** Pemilik lama memakai slug itu lagi: penahanannya dihapus. */
    public function lepas(string $slug, int $penggunaId): void
    {
        SlugDitahan::query()->where('slug', $slug)->where('user_id', $penggunaId)->delete();
    }

    /** Undangan dihapus: slug saat ini ditahan; baris lain yang mengarah ke undangan ini tidak lagi bisa mengalihkan. */
    public function tahanKarenaHapus(Undangan $u): void
    {
        $this->tahanSemua(collect([$u]), (int) $u->user_id);
    }

    /** Akun dihapus (cascade basis data melewati event model Undangan): semua slug miliknya ditahan atas nama akun itu. */
    public function tahanSemuaMilik(User $user): void
    {
        $this->tahanSemua(Undangan::query()->where('user_id', $user->id)->get(['id', 'user_id', 'slug']), (int) $user->id);
    }

    /** Hapus penahanan yang sudah lewat. Idempoten. */
    public function bersihkan(): int
    {
        return SlugDitahan::query()->where('ditahan_sampai', '<=', now())->delete();
    }

    private function tahanSemua($daftar, int $penggunaId): void
    {
        if ($daftar->isEmpty()) {
            return;
        }
        foreach ($daftar as $u) {
            if ($u->slug !== null && $u->slug !== '') {
                $this->tahan($u->slug, $penggunaId, null, SlugDitahan::HAPUS);
            }
        }
        SlugDitahan::query()->whereIn('undangan_id', $daftar->pluck('id')->all())->update(['undangan_id' => null]);
    }
}
