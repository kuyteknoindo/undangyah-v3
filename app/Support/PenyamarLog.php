<?php

namespace App\Support;

/**
 * Penyaring tampilan metadata log audit. `LogAksiAdmin` sudah tidak menulis rahasia; ini lapis kedua saat BACA, supaya
 * data lama atau pencatat baru yang lalai tetap tidak membocorkan: kunci berbahaya disembunyikan, nomor panjang
 * (rekening, HP) hanya menyisakan 4 digit terakhir, dan string mirip token disembunyikan. Email pihak ketiga tetap seperti di log.
 */
class PenyamarLog
{
    public const TERSEMBUNYI = '[disembunyikan]';

    /** Potongan nama kunci (dipisah `_`, `-`, spasi) yang nilainya tidak pernah ditampilkan. */
    private const KUNCI_RAHASIA = ['password', 'passwd', 'pass', 'sandi', 'token', 'secret', 'rahasia', 'otp', 'hash', 'apikey', 'authorization', 'cookie', 'cvv', 'csrf', 'signature', 'pin'];

    /** Kunci isi pesan pribadi. */
    private const KUNCI_ISI = ['isi', 'pesan', 'body', 'konten', 'message', 'content', 'ucapan'];

    /** Kunci yang bernilai nomor rekening/telepon. */
    private const KUNCI_NOMOR = ['rekening', 'rek', 'norek', 'nomor', 'hp', 'telepon', 'phone', 'wa', 'whatsapp', 'tujuan', 'nik', 'kartu', 'card', 'iban'];

    /**
     * @param  array<mixed>|null  $data
     * @return array<mixed>|null
     */
    public static function saring(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $hasil = [];
        foreach ($data as $kunci => $nilai) {
            $hasil[$kunci] = self::saringNilai(is_string($kunci) ? $kunci : '', $nilai);
        }

        return $hasil;
    }

    private static function saringNilai(string $kunci, mixed $nilai, int $dalam = 0): mixed
    {
        if ($nilai === null || $nilai === '') {
            return $nilai;
        }
        $bagian = self::bagian($kunci);
        if (array_intersect($bagian, self::KUNCI_RAHASIA) || preg_match('/password|passwd|sandi|token|secret|apikey/i', $kunci)) {
            return self::TERSEMBUNYI;
        }
        if (array_intersect($bagian, self::KUNCI_ISI)) {
            return self::TERSEMBUNYI;
        }
        if (is_array($nilai)) {
            if ($dalam >= 6) {
                return self::TERSEMBUNYI;
            }
            $hasil = [];
            foreach ($nilai as $k => $v) {
                // Kunci induk bernomor (mis. rekening: {...}) ikut menurunkan sifat nomor ke anaknya.
                $hasil[$k] = self::saringNilai(is_string($k) ? $k : ($bagian !== [] ? $kunci : ''), $v, $dalam + 1);
            }

            return $hasil;
        }
        if (is_string($nilai)) {
            return self::saringTeks($kunci, $bagian, $nilai);
        }

        return $nilai;
    }

    /** @param  list<string>  $bagian */
    private static function saringTeks(string $kunci, array $bagian, string $nilai): string
    {
        $ringkas = trim($nilai);
        // Kode OTP: kunci "kode" dengan 4-8 digit saja. Kode katalog (voucher, metode) tetap tampil.
        if (in_array('kode', $bagian, true) && preg_match('/^\d{4,8}$/', $ringkas)) {
            return self::TERSEMBUNYI;
        }
        // Kunci nomor + isi hanya angka/pemisah: samarkan. Nomor pesanan ("INV-2026-00001") berhuruf, jadi tidak ikut.
        $digit = strlen(preg_replace('/\D/', '', $ringkas) ?? '');
        $hanyaAngka = (bool) preg_match('/^[\d\s\-+().*]+$/', $ringkas);
        $tanggal = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $ringkas);
        if ($hanyaAngka && ! $tanggal && ($digit >= 10 || (array_intersect($bagian, self::KUNCI_NOMOR) && $digit >= 5))) {
            return (string) Samarkan::nomor($ringkas);
        }
        // String panjang tanpa spasi yang tampak seperti token/hash/kunci.
        if (strlen($ringkas) >= 32 && ! str_contains($ringkas, ' ') && preg_match('/^[A-Za-z0-9+\/=_\-.:]+$/', $ringkas) && ! filter_var($ringkas, FILTER_VALIDATE_EMAIL) && ! preg_match('/^\d{4}-\d{2}-\d{2}T/', $ringkas)) {
            return self::TERSEMBUNYI;
        }

        return $nilai;
    }

    /** @return list<string> */
    private static function bagian(string $kunci): array
    {
        return array_values(array_filter(preg_split('/[^a-z0-9]+/', mb_strtolower($kunci)) ?: []));
    }
}
