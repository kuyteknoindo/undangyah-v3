<?php

namespace App\Services;

use App\Support\NomorWa;

/** Baca dan tulis CSV daftar tamu dengan batas ukuran dan perlindungan CSV injection. */
class CsvTamu
{
    public const MAKS_BYTE = 262144;

    public const MAKS_BARIS = 500;

    public const MAKS_NAMA = 100;

    public const MAKS_GRUP = 60;

    public const MAKS_JUMLAH = 20;

    private const ALIAS = [
        'nama' => ['nama', 'name', 'tamu', 'nama tamu'],
        'hp' => ['hp', 'wa', 'whatsapp', 'nomor whatsapp', 'no wa', 'no whatsapp', 'no hp', 'nomor hp', 'nomor', 'telepon', 'telp', 'phone'],
        'grup' => ['grup', 'group', 'kelompok', 'kategori'],
        'jumlah' => ['jumlah', 'jumlah tamu', 'jumlah orang', 'pax', 'orang'],
    ];

    /**
     * @param  array<string,true>  $kunciAda  kunci "nama|hp" tamu yang sudah ada (lihat kunci())
     * @return array{baris: list<array{nama:string,hp:?string,grup:?string,jumlah:int}>, gagal: list<array{baris:int,alasan:string}>, duplikat: int, galat: ?string}
     */
    public function urai(string $isi, array $kunciAda = []): array
    {
        $hasil = ['baris' => [], 'gagal' => [], 'duplikat' => 0, 'galat' => null];
        if (strlen($isi) > self::MAKS_BYTE) {
            $hasil['galat'] = 'Berkas terlalu besar (maksimal '.(self::MAKS_BYTE / 1024).' KB).';

            return $hasil;
        }
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;
        if (! mb_check_encoding($isi, 'UTF-8')) {
            $isi = mb_convert_encoding($isi, 'UTF-8', 'Windows-1252');
        }
        if (str_contains($isi, "\0")) {
            $hasil['galat'] = 'Berkas bukan CSV teks yang valid.';

            return $hasil;
        }

        $pemisah = $this->pemisah($isi);
        $aliran = fopen('php://temp', 'r+');
        fwrite($aliran, $isi);
        rewind($aliran);

        $baris = [];
        $nomor = 0;
        while (($sel = fgetcsv($aliran, 4096, $pemisah, '"', '')) !== false) {
            $nomor++;
            if ($sel === [null] || ! array_filter($sel, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $baris[] = [$nomor, $sel];
            if (count($baris) > self::MAKS_BARIS + 1) {
                break;
            }
        }
        fclose($aliran);

        if ($baris === []) {
            $hasil['galat'] = 'Berkas kosong. Isi minimal satu baris tamu.';

            return $hasil;
        }

        $peta = $this->petaKolom($baris[0][1]);
        if ($peta !== null) {
            array_shift($baris);
        } else {
            $peta = ['nama' => 0, 'hp' => 1, 'grup' => 2, 'jumlah' => 3];
        }
        if ($baris === []) {
            $hasil['galat'] = 'Berkas hanya berisi judul kolom, tanpa data tamu.';

            return $hasil;
        }
        if (count($baris) > self::MAKS_BARIS) {
            $hasil['galat'] = 'Terlalu banyak baris (maksimal '.self::MAKS_BARIS.' tamu per impor). Pecah menjadi beberapa berkas.';

            return $hasil;
        }

        $terlihat = $kunciAda;
        foreach ($baris as [$no, $sel]) {
            $ambil = fn (string $k) => isset($peta[$k]) ? $this->bersih((string) ($sel[$peta[$k]] ?? '')) : '';
            $nama = $ambil('nama');
            $hp = $ambil('hp');
            $grup = $ambil('grup');
            $jumlah = $ambil('jumlah');

            if ($nama === '') {
                $hasil['gagal'][] = ['baris' => $no, 'alasan' => 'Nama kosong.'];

                continue;
            }
            if (mb_strlen($nama) > self::MAKS_NAMA) {
                $hasil['gagal'][] = ['baris' => $no, 'alasan' => 'Nama lebih dari '.self::MAKS_NAMA.' karakter.'];

                continue;
            }
            $hpSimpan = null;
            if ($hp !== '') {
                $hpSimpan = $this->normalisasiHp($hp);
                if ($hpSimpan === null) {
                    $hasil['gagal'][] = ['baris' => $no, 'alasan' => 'Nomor WhatsApp "'.mb_substr($hp, 0, 20).'" tidak valid.'];

                    continue;
                }
            }
            if (mb_strlen($grup) > self::MAKS_GRUP) {
                $hasil['gagal'][] = ['baris' => $no, 'alasan' => 'Grup lebih dari '.self::MAKS_GRUP.' karakter.'];

                continue;
            }
            $jumlahAngka = 1;
            if ($jumlah !== '') {
                if (! ctype_digit($jumlah) || (int) $jumlah < 1 || (int) $jumlah > self::MAKS_JUMLAH) {
                    $hasil['gagal'][] = ['baris' => $no, 'alasan' => 'Jumlah harus angka 1 sampai '.self::MAKS_JUMLAH.'.'];

                    continue;
                }
                $jumlahAngka = (int) $jumlah;
            }

            $kunci = self::kunci($nama, $hpSimpan);
            if (isset($terlihat[$kunci])) {
                $hasil['duplikat']++;

                continue;
            }
            $terlihat[$kunci] = true;
            $hasil['baris'][] = ['nama' => $nama, 'hp' => $hpSimpan, 'grup' => $grup !== '' ? $grup : null, 'jumlah' => $jumlahAngka];
        }

        return $hasil;
    }

    /** Kunci deduplikasi: nama (tanpa beda huruf besar/kecil dan spasi ganda) + nomor. */
    public static function kunci(string $nama, ?string $hp): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama)).'|'.($hp ?? '');
    }

    /** Nomor ke bentuk tersimpan 62812...; null bila bukan nomor seluler Indonesia yang masuk akal. */
    public static function normalisasiHp(string $nomor): ?string
    {
        $inti = NomorWa::ringkas($nomor);

        return preg_match('/^8\d{8,12}$/', $inti) ? '62'.$inti : null;
    }

    /** Hapus karakter kontrol, rapikan spasi. */
    public static function bersih(string $teks): string
    {
        $teks = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', ' ', $teks) ?? $teks;

        return trim(preg_replace('/\s+/u', ' ', $teks) ?? $teks);
    }

    /**
     * Awali sel yang bisa dibaca spreadsheet sebagai rumus (=, +, -, @, tab, CR) dengan apostrof.
     */
    public static function sel(string|int|float|null $nilai): string
    {
        $teks = (string) $nilai;
        if ($teks !== '' && in_array($teks[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$teks;
        }

        return $teks;
    }

    /**
     * @param  list<string>  $judul
     * @param  iterable<list<string|int|float|null>>  $baris
     */
    public function tulis(array $judul, iterable $baris): string
    {
        $aliran = fopen('php://temp', 'r+');
        fwrite($aliran, "\xEF\xBB\xBF");
        fputcsv($aliran, array_map(fn ($j) => self::sel($j), $judul), ',', '"', '', "\r\n");
        foreach ($baris as $row) {
            fputcsv($aliran, array_map(fn ($v) => self::sel($v), $row), ',', '"', '', "\r\n");
        }
        rewind($aliran);
        $isi = (string) stream_get_contents($aliran);
        fclose($aliran);

        return $isi;
    }

    private function pemisah(string $isi): string
    {
        $pertama = strtok($isi, "\n") ?: '';
        $terbaik = ',';
        $maks = -1;
        foreach ([',', ';', "\t"] as $p) {
            $n = substr_count($pertama, $p);
            if ($n > $maks) {
                $maks = $n;
                $terbaik = $p;
            }
        }

        return $terbaik;
    }

    /** @return array<string,int>|null peta kolom bila baris pertama adalah judul kolom */
    private function petaKolom(array $sel): ?array
    {
        $peta = [];
        foreach ($sel as $i => $judul) {
            $j = mb_strtolower(trim((string) preg_replace('/[^a-z0-9 ]+/iu', ' ', mb_strtolower((string) $judul))));
            $j = trim(preg_replace('/\s+/', ' ', $j) ?? $j);
            foreach (self::ALIAS as $kolom => $alias) {
                if (in_array($j, $alias, true) && ! isset($peta[$kolom])) {
                    $peta[$kolom] = $i;
                }
            }
        }

        return isset($peta['nama']) ? $peta : null;
    }
}
