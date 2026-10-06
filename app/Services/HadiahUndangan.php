<?php

namespace App\Services;

use App\Models\Undangan;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Rekening/e-wallet dan alamat kado hidup di `undangan.isi` (bidang kd{id}_bank/no/atas, kdPenerima/kdAlamat/kdTelp,
 * `_daftar.kd`) supaya editor dan undangan publik memakai sumber yang sama. Tampil di undangan setelah diterbitkan ulang.
 */
class HadiahUndangan
{
    public const MAKS_REKENING = 10;

    public const BANK = ['BCA', 'Mandiri', 'BNI', 'BRI', 'BSI', 'CIMB Niaga', 'Permata', 'Danamon', 'Bank Jago', 'Jenius (BTPN)', 'SeaBank', 'Bank Lainnya'];

    public const EWALLET = ['GoPay', 'OVO', 'DANA', 'ShopeePay', 'LinkAja'];

    public function __construct(private PenerbitUndangan $penerbit) {}

    public static function ewallet(string $nama): bool
    {
        return in_array($nama, self::EWALLET, true);
    }

    /** "081234567890" -> "0812 3456 7890". */
    public static function formatNomor(string $angka): string
    {
        return trim(chunk_split(preg_replace('/\D/', '', $angka) ?? '', 4, ' '));
    }

    /** @return list<array{id:int,bank:string,no:string,an:string,jenis:string}> */
    public function rekening(Undangan $u): array
    {
        $isi = is_array($u->isi) ? $u->isi : [];
        $hasil = [];
        foreach ($this->idRekening($isi) as $id) {
            $bank = trim((string) ($isi["kd{$id}_bank"] ?? ''));
            $hasil[] = [
                'id' => $id, 'bank' => $bank, 'no' => trim((string) ($isi["kd{$id}_no"] ?? '')), 'an' => trim((string) preg_replace('/^a\.?\s?n\.?\s*/i', '', trim((string) ($isi["kd{$id}_atas"] ?? '')))),
                'jenis' => self::ewallet($bank) ? 'ewallet' : 'bank',
            ];
        }

        return $hasil;
    }

    /** @return ?array{penerima:string,hp:string,alamat:string} */
    public function alamat(Undangan $u): ?array
    {
        $isi = is_array($u->isi) ? $u->isi : [];
        $alamat = trim((string) ($isi['kdAlamat'] ?? ''));
        if ($alamat === '') {
            return null;
        }

        return ['penerima' => trim((string) ($isi['kdPenerima'] ?? '')), 'hp' => trim((string) ($isi['kdTelp'] ?? '')), 'alamat' => $alamat];
    }

    /** @return list<int> */
    private function idRekening(array $isi): array
    {
        $ids = $isi['_daftar']['kd'] ?? [];

        return is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0))) : [];
    }

    public function adaRekening(Undangan $u, int $id): bool
    {
        return in_array($id, $this->idRekening(is_array($u->isi) ? $u->isi : []), true);
    }

    /** Simpan/ubah rekening. @return int id item */
    public function simpanRekening(Undangan $u, ?int $id, string $bank, string $nomor, string $atasNama): int
    {
        $hasil = 0;
        $this->ubah($u, function (array $isi) use ($id, $bank, $nomor, $atasNama, &$hasil) {
            $ids = $this->idRekening($isi);
            if ($id === null) {
                $id = ($ids ? max($ids) : 0) + 1;
                $ids[] = $id;
            }
            $isi["kd{$id}_bank"] = $bank;
            $isi["kd{$id}_no"] = $nomor;
            // Bentuk "a.n. Nama" sama dengan isian awal editor; halaman ini menampilkannya tanpa awalan.
            $isi["kd{$id}_atas"] = 'a.n. '.$atasNama;
            $isi['_daftar']['kd'] = $ids;
            $hasil = $id;

            return $isi;
        });

        return $hasil;
    }

    public function hapusRekening(Undangan $u, int $id): void
    {
        $this->ubah($u, function (array $isi) use ($id) {
            $isi['_daftar']['kd'] = array_values(array_diff($this->idRekening($isi), [$id]));
            foreach (['bank', 'no', 'atas'] as $b) {
                unset($isi["kd{$id}_{$b}"]);
            }

            return $isi;
        });
    }

    public function simpanAlamat(Undangan $u, string $penerima, string $hp, string $alamat): void
    {
        $this->ubah($u, fn (array $isi) => [...$isi, 'kdPenerima' => $penerima, 'kdTelp' => $hp, 'kdAlamat' => $alamat]);
    }

    public function hapusAlamat(Undangan $u): void
    {
        $this->simpanAlamat($u, '', '', '');
    }

    /** Ubah `isi` di bawah kunci baris supaya dua perubahan bersamaan tidak saling menimpa; tandai "ada perubahan". */
    private function ubah(Undangan $u, Closure $fn): void
    {
        DB::transaction(function () use ($u, $fn) {
            $segar = Undangan::query()->whereKey($u->id)->where('user_id', $u->user_id)->lockForUpdate()->firstOrFail();
            $segar->isi = $fn(is_array($segar->isi) ? $segar->isi : []);
            $this->penerbit->sinkron($segar);
            $segar->draf_disimpan_at = now();
            $segar->save();
            $u->refresh();
        });
    }
}
