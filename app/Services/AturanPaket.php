<?php

namespace App\Services;

use App\Models\Paket;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Satu-satunya sumber urutan tingkat paket (Gratis paling rendah, lalu menurut harga, urutan, id di katalog `paket`)
 * dan aturan beli: paket yang sama = perpanjangan, lebih tinggi = upgrade, LEBIH RENDAH selagi paket yang lebih tinggi
 * masih aktif = ditolak. Paket berbayar yang masa aktifnya habis dihitung Gratis (`User::paketAktif()`), jadi beli
 * paket rendah sesudahnya boleh. Add-on tidak terkena aturan ini.
 */
class AturanPaket
{
    /** Peringkat bawaan bila katalog paket kosong (mis. sebelum seeder berjalan). */
    private const PERINGKAT_BAWAAN = ['gratis' => 0, 'premium' => 1, 'deluxe' => 2];

    /** @var array<string,int>|null */
    private ?array $peringkat = null;

    /** @return array<string,int> kode => urutan naik */
    public function peringkat(): array
    {
        if ($this->peringkat === null) {
            $kode = Paket::query()->orderBy('harga')->orderBy('urutan')->orderBy('id')->pluck('kode')->all();
            usort($kode, fn ($a, $b) => ($b === 'gratis') <=> ($a === 'gratis'));
            $this->peringkat = $kode === [] ? self::PERINGKAT_BAWAAN : array_flip(array_values($kode));
        }

        return $this->peringkat;
    }

    /** Buang cache bila katalog berubah di tengah proses yang sama (tes, antrean). */
    public function lupakan(): void
    {
        $this->peringkat = null;
    }

    public function lebihRendah(string $kode, string $aktif): bool
    {
        $p = $this->peringkat();

        return isset($p[$kode], $p[$aktif]) && $p[$kode] < $p[$aktif];
    }

    /**
     * Apakah membeli paket $kode bentrok dengan paket lebih tinggi yang masih aktif milik $user?
     * Null bila boleh. Dibaca dari pengguna SAAT INI, jadi pemanggil di dalam transaksi harus memberi `User` yang terkunci.
     *
     * @return array{aktif:string,nama_aktif:string,nama_beli:string,sampai:?CarbonInterface,pesan:string}|null
     */
    public function bentrok(User $user, string $kode): ?array
    {
        $aktif = $user->paketAktif();
        if ($aktif === 'gratis' || ! $this->lebihRendah($kode, $aktif)) {
            return null;
        }
        $nama = Paket::whereIn('kode', [$aktif, $kode])->pluck('nama', 'kode');
        $namaAktif = 'Paket '.($nama[$aktif] ?? ucfirst($aktif));
        $namaBeli = 'Paket '.($nama[$kode] ?? ucfirst($kode));
        $sampai = $user->paket_sampai;

        return [
            'aktif' => $aktif,
            'nama_aktif' => $namaAktif,
            'nama_beli' => $namaBeli,
            'sampai' => $sampai,
            'pesan' => $namaAktif.' kamu masih aktif '.self::keterangan($sampai).', jadi '.$namaBeli.' tidak bisa dibeli. '
                .'Tunggu sampai masa aktifnya berakhir, atau pilih add-on saja.',
        ];
    }

    /** "sampai 12 Okt 2027" atau "tanpa batas waktu". */
    public static function keterangan(?CarbonInterface $sampai): string
    {
        return $sampai ? 'sampai '.$sampai->copy()->locale('id')->translatedFormat('j M Y') : 'tanpa batas waktu';
    }
}
