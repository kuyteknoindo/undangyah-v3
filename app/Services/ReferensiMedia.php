<?php

namespace App\Services;

use App\Models\Lagu;
use App\Models\Media;
use App\Models\Tema;
use App\Models\Undangan;
use Illuminate\Support\Collection;

/**
 * Mencari di mana sebuah media dirujuk: isi/terbitan undangan (`_foto`, `_galeri`, `_latarTetap`, musik, dll. -
 * dicocokkan lewat path unik berkas), dokumen tema, dan katalog musik. Dipakai halaman Media/Storage dan editor.
 */
class ReferensiMedia
{
    private const TUNDA = 200;

    /**
     * @param  iterable<Media>  $media
     * @return array<int,list<string>> id media => daftar rujukan ("undangan:12", "tema:3", "lagu:5"); hanya yang dirujuk
     */
    public function cari(iterable $media): array
    {
        $daftar = collect($media)->filter(fn ($m) => $m instanceof Media)->keyBy('id');
        if ($daftar->isEmpty()) {
            return [];
        }
        $hasil = [];

        $this->dariLagu($daftar, $hasil);
        $this->dariTema($daftar, $hasil);

        // Media milik pengguna hanya bisa dipakai di undangannya sendiri; aset sistem bisa di undangan siapa pun.
        foreach ($daftar->groupBy(fn (Media $m) => $m->user_id ?? 0) as $pemilik => $kelompok) {
            $kueri = Undangan::query()->select(['id', 'isi', 'terbitan'])->when($pemilik, fn ($q) => $q->where('user_id', $pemilik));
            $kueri->chunkById(self::TUNDA, function ($undangan) use ($kelompok, &$hasil) {
                foreach ($undangan as $u) {
                    $teks = json_encode([$u->isi, $u->terbitan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                    foreach ($kelompok as $m) {
                        if (str_contains($teks, $m->path)) {
                            $hasil[$m->id][] = 'undangan:'.$u->id;
                        }
                    }
                }
            });
        }

        return $hasil;
    }

    public function dirujuk(Media $media): bool
    {
        return $this->cari([$media]) !== [];
    }

    /** Ringkasan rujukan untuk pesan peringatan: "2 undangan, 1 lagu". */
    public function ringkas(array $rujukan): string
    {
        $jumlah = [];
        foreach ($rujukan as $r) {
            $jenis = explode(':', $r)[0];
            $jumlah[$jenis] = ($jumlah[$jenis] ?? 0) + 1;
        }
        $label = ['undangan' => 'undangan', 'tema' => 'tema', 'lagu' => 'lagu'];

        return collect($jumlah)->map(fn ($n, $j) => $n.' '.($label[$j] ?? $j))->implode(', ');
    }

    /**
     * Setel `dipakai` media milik satu pengguna sesuai rujukan nyata di undangannya. Dipanggil editor setelah menyimpan
     * atau mengganti foto agar penanda tidak basi. Mengembalikan jumlah media yang berubah.
     */
    public function sinkronPemilik(int $userId): int
    {
        $ubah = 0;
        Media::where('user_id', $userId)->orderBy('id')->chunkById(500, function ($kelompok) use (&$ubah) {
            $rujukan = $this->cari($kelompok);
            foreach ($kelompok as $m) {
                $pakai = isset($rujukan[$m->id]);
                if ($m->dipakai !== $pakai) {
                    $m->forceFill(['dipakai' => $pakai])->save();
                    $ubah++;
                }
            }
        });

        return $ubah;
    }

    private function dariLagu(Collection $daftar, array &$hasil): void
    {
        Lagu::whereIn('media_id', $daftar->keys())->get(['id', 'media_id'])
            ->each(function (Lagu $l) use (&$hasil) {
                $hasil[$l->media_id][] = 'lagu:'.$l->id;
            });
    }

    private function dariTema(Collection $daftar, array &$hasil): void
    {
        Tema::withTrashed()->get()->each(function (Tema $t) use ($daftar, &$hasil) {
            $teks = json_encode($t->getAttributes(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
            foreach ($daftar as $m) {
                if (str_contains($teks, $m->path)) {
                    $hasil[$m->id][] = 'tema:'.$t->id;
                }
            }
        });
    }
}
