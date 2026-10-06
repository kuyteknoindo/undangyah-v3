<?php

namespace App\Services;

use App\Models\Tema;
use App\Models\Undangan;
use App\Support\SkemaUndangan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Terbit/batal terbit undangan. Terbit = salinan beku (isi + bagian + tema + pengaturan) di kolom `terbitan`,
 * jadi perubahan tema oleh admin atau suntingan draf berikutnya tidak mengubah yang dilihat tamu.
 */
class PenerbitUndangan
{
    public function __construct(private IsiUndangan $isian, private SlugUndangan $slug, private KuotaUndangan $kuota) {}

    /** Tanda tangan keadaan draf: sama dengan terbitan bila tak ada perubahan (urutan kunci tidak berpengaruh). */
    public function tanda(Undangan $u): string
    {
        return sha1(json_encode($this->urut([
            'isi' => $u->isi ?? [], 'bagian' => $u->bagian ?? [], 'pengaturan' => $u->pengaturan ?? [],
            'tema' => $u->tema_id, 'judul' => $u->judul,
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function urut(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        $v = array_map(fn ($x) => $this->urut($x), $v);
        if (! array_is_list($v)) {
            ksort($v);
        }

        return $v;
    }

    /** Hitung ulang penanda "ada perubahan belum diterbitkan" (tanpa menyimpan). */
    public function sinkron(Undangan $u): void
    {
        $u->ada_perubahan = $u->sudahTerbit() && (($u->terbitan['tanda'] ?? null) !== $this->tanda($u));
    }

    /** @return list<array{teks:string,kunci:string}> */
    public function blokir(Undangan $u): array
    {
        $isi = array_merge(SkemaUndangan::nilaiAwal(), $u->isi ?? []);
        $masalah = $this->isian->blokir($isi, (string) $u->judul);

        if ($u->tema_id !== null && ($tema = Tema::withTrashed()->find($u->tema_id)) && $this->kuota->temaTerkunci($tema, $u->pengguna)) {
            $masalah[] = ['teks' => 'Tema "'.$tema->nama.'" tidak tersedia di paketmu', 'kunci' => 'tema'];
        }

        return $masalah;
    }

    /** Saran yang tidak menghalangi terbit. @return list<array{teks:string,kunci:string}> */
    public function peringatan(Undangan $u): array
    {
        $isi = array_merge(SkemaUndangan::nilaiAwal(), $u->isi ?? []);
        $daftar = $isi['_daftar'] ?? [];
        $sembunyi = $u->bagian['sembunyi'] ?? [];
        $saran = [];
        foreach (SkemaUndangan::bagian() as $id => $def) {
            if (($def['req'] ?? false) && ! in_array($id, $sembunyi, true) && ! $this->isian->status($id, $isi, $daftar)['done']) {
                $saran[] = ['teks' => 'Bagian '.$def['name'].' belum lengkap', 'kunci' => 'sec:'.$id];
            }
        }
        if (! $u->tamu()->exists()) {
            $saran[] = ['teks' => 'Belum ada tamu. Tambahkan daftar tamu agar tiap tamu punya tautan pribadi.', 'kunci' => 'tamu'];
        }

        return $saran;
    }

    /**
     * Terbitkan (atau terbitkan ulang) draf saat ini.
     *
     * @throws ValidationException bila slug tidak sah/bentrok atau syarat wajib belum lengkap
     */
    public function terbitkan(Undangan $u, string $slug): Undangan
    {
        $slug = trim(mb_strtolower($slug));
        if ($pesan = $this->slug->galat($slug, $u->id)) {
            throw ValidationException::withMessages(['slug' => $pesan]);
        }
        if ($blokir = $this->blokir($u)) {
            throw ValidationException::withMessages(['umum' => $blokir[0]['teks'].'.']);
        }

        $tema = $u->tema_id ? Tema::withTrashed()->find($u->tema_id) : null;
        $u->slug = $slug;
        $u->terbitan = [
            'judul' => $u->judul,
            'slug' => $slug,
            'isi' => $u->isi ?? [],
            'bagian' => $u->bagian ?? [],
            'pengaturan' => $u->pengaturan ?? [],
            'tema' => $tema?->only(['id', 'kode', 'nama', 'tier', 'warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks', 'font_judul', 'latar_tetap', 'dokumen', 'versi']),
            'tanda' => $this->tanda($u),
            'diterbitkan_at' => now()->toIso8601String(),
        ];
        $u->status = Undangan::TERBIT;
        $u->diterbitkan_at = now();
        $u->ada_perubahan = false;
        $langkah = $u->langkah ?? [];
        $u->langkah = array_merge($langkah, ['terbit' => true]);

        try {
            $u->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Alamat ini sudah dipakai undangan lain.']);
        }

        return $u;
    }

    /** Batalkan terbit: kembali ke draf, tamu tidak lagi bisa membuka. Isi draf tetap utuh. */
    public function jadikanDraf(Undangan $u): Undangan
    {
        $u->status = Undangan::DRAF;
        $u->terbitan = null;
        $u->diterbitkan_at = null;
        $u->ada_perubahan = false;
        $u->langkah = array_merge($u->langkah ?? [], ['terbit' => false]);
        $u->save();

        return $u;
    }
}
