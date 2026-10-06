<?php

namespace App\Services;

use App\Support\SkemaUndangan;
use Illuminate\Support\Carbon;

/**
 * Aturan isian undangan di atas skema (SkemaUndangan): kunci mana yang sah, pembersihan nilai per jenis bidang,
 * status kelengkapan bagian, dan pemeriksaan sebelum terbit. Tidak menyentuh database.
 *
 * Kunci sah = 125 bidang tetap + "{tpl}{id}_{kunci}" untuk id yang ada di daftar berulang. Kunci lain ditolak.
 */
class IsiUndangan
{
    public const MAKS_TEKS = 255;

    public const MAKS_AREA = 2000;

    public const MAKS_URL = 500;

    public const MAKS_ITEM = 30;

    /** Pilihan "Deskripsi saat dibagikan" (scDesc): {p}/{w}/{tgl}/{lokasi} diisi saat ditampilkan (lihat isiLabel). */
    public const OPSI_DESKRIPSI = [
        ['d1', 'Kami mengundang Anda ke pernikahan {p} & {w}. Buka untuk melihat detail acara.'],
        ['d2', '{p} & {w} akan menikah pada {tgl}. Mohon doa dan kehadiran Anda.'],
        ['d3', 'Dengan memohon doa restu, kami mengundang Anda hadir di {lokasi} pada {tgl}.'],
        ['d4', 'Satu hati, satu langkah baru. Bergabunglah di hari bahagia {p} & {w}.'],
        ['d5', 'Undangan digital pernikahan {p} & {w}. Buka untuk melihat acara dan konfirmasi kehadiran.'],
        ['d6', 'Hari bahagia {p} & {w} · {tgl} · {lokasi}.'],
    ];

    public const PESAN_URL = 'Link harus diawali http:// atau https:// dan berupa alamat yang lengkap.';

    /**
     * Metadata satu bidang, atau null bila kunci tidak sah.
     *
     * @param  array<string,list<int>>  $daftar  tpl => id item
     * @return array{g:string,label:string,v:string,kind:string,rows:int,opt:bool,canvas:bool,hint:string,opts:?array}|null
     */
    public function meta(string $kunci, array $daftar): ?array
    {
        if (isset(SkemaUndangan::bidang()[$kunci])) {
            $b = SkemaUndangan::bidang()[$kunci];

            return [
                'g' => $b['g'], 'label' => $b['label'], 'v' => (string) ($b['v'] ?? ''), 'kind' => $b['kind'] ?? 'text', 'rows' => (int) ($b['rows'] ?? 0),
                'opt' => (bool) ($b['opt'] ?? false), 'canvas' => ($b['canvas'] ?? true) !== false, 'hint' => (string) ($b['hint'] ?? ''), 'opts' => $kunci === 'musLagu' ? KatalogLagu::opsiEditor() : ($b['opts'] ?? ($kunci === 'scDesc' ? self::OPSI_DESKRIPSI : null)),
            ];
        }

        if (! preg_match('/^([a-z]{2})(\d{1,9})_([A-Za-z0-9]+)$/', $kunci, $m)) {
            return null;
        }
        [, $tpl, $id, $bidang] = $m;
        $templat = SkemaUndangan::templat()[$tpl] ?? null;
        if ($templat === null || ! in_array((int) $id, $daftar[$tpl] ?? [], true)) {
            return null;
        }
        foreach ($templat['f'] as $f) {
            if ($f[0] === $bidang) {
                $o = $f[3] ?? [];

                return [
                    'g' => $templat['g'], 'label' => $f[1], 'v' => (string) $f[2], 'kind' => $o['kind'] ?? 'text', 'rows' => (int) ($o['rows'] ?? 0),
                    'opt' => (bool) ($o['opt'] ?? false), 'canvas' => ($o['canvas'] ?? true) !== false, 'hint' => (string) ($o['hint'] ?? ''), 'opts' => $o['opts'] ?? null,
                ];
            }
        }

        return null;
    }

    /** Isi tempat penampung {p} {w} {tgl} {lokasi} pada label pilihan dengan data undangan. */
    public function isiLabel(string $label, array $isi): string
    {
        if (! str_contains($label, '{')) {
            return $label;
        }

        return strtr($label, [
            '{p}' => trim((string) ($isi['priaP'] ?? '')) ?: 'Pria',
            '{w}' => trim((string) ($isi['wanitaP'] ?? '')) ?: 'Wanita',
            '{tgl}' => trim((string) ($isi['tglCover'] ?? '')) ?: '14 Februari 2027',
            '{lokasi}' => trim((string) ($isi['gcLokasi'] ?? '')) ?: 'lokasi acara',
        ]);
    }

    /** Kunci satu item daftar berulang, urut sesuai templat. @return list<string> */
    public function kunciItem(string $tpl, int $id): array
    {
        return array_map(fn ($f) => "{$tpl}{$id}_{$f[0]}", SkemaUndangan::templat()[$tpl]['f'] ?? []);
    }

    /** Bidang tetap suatu bagian. @return list<string> */
    public function kunciTetap(string $bagian): array
    {
        return array_keys(SkemaUndangan::bidangBagian($bagian));
    }

    /** Semua kunci suatu bagian (tetap + item daftar). @return list<string> */
    public function kunciBagian(string $bagian, array $daftar): array
    {
        $kunci = $this->kunciTetap($bagian);
        foreach (SkemaUndangan::daftarBagian()[$bagian] ?? [] as $sl) {
            foreach ($daftar[$sl['t']] ?? [] as $id) {
                array_push($kunci, ...$this->kunciItem($sl['t'], (int) $id));
            }
        }

        return $kunci;
    }

    /** Nilai awal item baru. @return array<string,string> */
    public function nilaiAwalItem(string $tpl, int $id): array
    {
        $nilai = [];
        foreach (SkemaUndangan::templat()[$tpl]['f'] as $f) {
            $nilai["{$tpl}{$id}_{$f[0]}"] = (string) $f[2];
        }

        return $nilai;
    }

    /**
     * Bersihkan satu nilai sesuai jenis bidangnya.
     *
     * @return array{0:?string,1:?string} [nilai bersih, pesan galat]; nilai null bila ditolak
     */
    public function bersihkan(mixed $nilai, array $meta): array
    {
        if (is_bool($nilai)) {
            $nilai = $nilai ? '1' : '0';
        }
        if (is_int($nilai) || is_float($nilai)) {
            $nilai = (string) $nilai;
        }
        if (! is_string($nilai)) {
            return [null, 'Nilai tidak valid.'];
        }
        if (! mb_check_encoding($nilai, 'UTF-8')) {
            return [null, 'Teks mengandung karakter yang tidak dikenali.'];
        }

        $s = str_replace(["\r\n", "\r"], "\n", $nilai);
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
        // Tag HTML dibuang; "<3" dan sejenisnya dibiarkan karena keluaran selalu di-escape.
        $s = (string) preg_replace('/<\/?[a-zA-Z!][^>]*>?/', '', $s);

        switch ($meta['kind']) {
            case 'url':
                $s = trim($s);
                if ($s === '') {
                    return ['', null];
                }
                if (mb_strlen($s) > self::MAKS_URL || ! $this->urlSah($s)) {
                    return [null, self::PESAN_URL];
                }

                return [$s, null];

            case 'select':
                $sah = array_map(fn ($o) => (string) $o[0], $meta['opts'] ?? []);

                return in_array($s, $sah, true) ? [$s, null] : [null, 'Pilihan tidak tersedia.'];

            case 'switch':
                return in_array($s, ['0', '1'], true) ? [$s, null] : [null, 'Nilai tidak valid.'];

            case 'date':
                if ($s === '') {
                    return ['', null];
                }
                try {
                    $t = Carbon::createFromFormat('!Y-m-d', $s);
                } catch (\Throwable) {
                    $t = false;
                }

                return $t && $t->format('Y-m-d') === $s ? [$s, null] : [null, 'Tanggal tidak valid.'];

            case 'time':
                if ($s === '') {
                    return ['', null];
                }

                return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s) ? [$s, null] : [null, 'Jam tidak valid (format JJ:MM).'];

            case 'color':
                return preg_match('/^#[0-9a-fA-F]{6}$/', $s) ? [strtoupper($s), null] : [null, 'Kode warna harus berformat #RRGGBB.'];
        }

        if ($meta['rows'] >= 2) {
            return [mb_substr($s, 0, self::MAKS_AREA), null];
        }

        return [mb_substr(trim((string) preg_replace('/\s*\n\s*/', ' ', $s)), 0, self::MAKS_TEKS), null];
    }

    /** Hanya http/https dengan host. Skema lain (javascript:, data:, dst.) ditolak. */
    public function urlSah(string $url): bool
    {
        if (preg_match('/\s/', $url) || ! preg_match('~^https?://~i', $url)) {
            return false;
        }
        $p = parse_url($url);

        return is_array($p) && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && ($p['host'] ?? '') !== '' && str_contains($p['host'], '.');
    }

    /**
     * Status kelengkapan bagian (port dari status() di desain). Bagian foto dan gallery belum dinilai (menunggu pustaka media).
     *
     * @return array{done:bool,any:bool}
     */
    public function status(string $id, array $isi, array $daftar): array
    {
        $nilai = fn (string $k): string => trim((string) ($isi[$k] ?? ''));
        $kunci = $this->kunciBagian($id, $daftar);
        $terisi = count(array_filter($kunci, fn ($k) => $nilai($k) !== ''));
        $wajibOk = true;
        foreach ($kunci as $k) {
            $m = $this->meta($k, $daftar);
            if ($m && ! $m['opt'] && $m['kind'] !== 'switch' && $nilai($k) === '') {
                $wajibOk = false;
                break;
            }
        }
        $n = fn (string $t): int => count($daftar[$t] ?? []);
        $gcLengkap = $nilai('gcJudul') !== '' && $nilai('gcTgl') !== '' && $nilai('gcMulai') !== '' && $nilai('gcSelesai') !== '' && $nilai('gcLokasi') !== '';

        return match ($id) {
            'mempelai' => ['done' => $wajibOk, 'any' => $terisi > 0],
            'acara' => ['done' => $wajibOk && $n('ev') > 0, 'any' => $terisi > 0],
            'lovestory' => ['done' => $n('ls') > 0 && $wajibOk, 'any' => $n('ls') > 0],
            'quotes' => ['done' => $nilai('quote') !== '', 'any' => $nilai('quote') !== ''],
            'musik' => ['done' => $nilai('musLagu') !== '' || $this->urlSah($nilai('musLink')), 'any' => $nilai('musLagu') !== '' || $this->urlSah($nilai('musLink'))],
            'rundown' => ['done' => $n('rd') > 0 && $wajibOk, 'any' => $n('rd') > 0],
            'turut' => ['done' => $n('tp') + $n('tw') > 0 && $wajibOk, 'any' => $n('tp') + $n('tw') > 0],
            'kado' => ['done' => $n('kd') > 0 && $wajibOk, 'any' => $n('kd') > 0],
            'livestream' => ['done' => $this->urlSah($nilai('lvLink')) && $nilai('lvTgl') !== '' && $nilai('lvMulai') !== '', 'any' => $nilai('lvLink') !== ''],
            'gcal' => ['done' => $gcLengkap, 'any' => $gcLengkap],
            'social' => ['done' => $n('ak') > 0 && $wajibOk, 'any' => $n('ak') > 0],
            'dresscode' => ['done' => $nilai('dcTema') !== '' && $n('wr') > 0, 'any' => $nilai('dcTema') !== '' || $n('wr') > 0],
            'rsvp' => ['done' => $nilai('rsTitle') !== '' && $nilai('rsBtn') !== '' && $nilai('wsTitle') !== '', 'any' => $nilai('rsTitle') !== '' && $nilai('rsBtn') !== '' && $nilai('wsTitle') !== ''],
            'tamu' => ['done' => $nilai('qrTitle') !== '' && $nilai('tmNama') !== '', 'any' => $nilai('qrTitle') !== '' && $nilai('tmNama') !== ''],
            'foto', 'gallery' => ['done' => false, 'any' => false],
            default => ['done' => true, 'any' => true],
        };
    }

    /**
     * Syarat wajib sebelum terbit.
     *
     * @return list<array{teks:string,kunci:string}>
     */
    public function blokir(array $isi, string $judul): array
    {
        $ada = fn (string $k): bool => trim((string) ($isi[$k] ?? '')) !== '';
        $m = [];
        if (trim($judul) === '') {
            $m[] = ['teks' => 'Nama undangan belum diisi', 'kunci' => 'judul'];
        }
        if (! $ada('priaP')) {
            $m[] = ['teks' => 'Nama panggilan pria belum diisi', 'kunci' => 'priaP'];
        }
        if (! $ada('wanitaP')) {
            $m[] = ['teks' => 'Nama panggilan wanita belum diisi', 'kunci' => 'wanitaP'];
        }
        if (! $ada('tglCover')) {
            $m[] = ['teks' => 'Tanggal acara belum diisi', 'kunci' => 'tglCover'];
        }

        return $m;
    }
}
