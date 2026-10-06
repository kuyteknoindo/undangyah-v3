<?php

namespace App\Services;

use App\Models\Pesanan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Penyaringan dan agregasi pesanan untuk halaman admin (Data Pesanan dan Invoices). Hanya SQL standar agar jalan di
 * PostgreSQL dan SQLite. "Kedaluwarsa" mencakup pesanan menunggu yang lewat batas waktu tetapi belum disapu perintah terjadwal.
 */
class KuerPesananAdmin
{
    public const STATUS = ['menunggu', 'lunas', 'kedaluwarsa', 'batal', 'refund'];

    public const PERIODE = ['', 'today', '7', '30', 'month', 'custom'];

    public const URUTAN = ['baru', 'lama', 'tinggi', 'rendah'];

    /** Ekspresi SQL tiap status efektif; ? = waktu sekarang. */
    private static function kondisi(string $status): string
    {
        return match ($status) {
            'menunggu' => "(status = 'menunggu' and (kedaluwarsa_at is null or kedaluwarsa_at > ?))",
            'kedaluwarsa' => "(status = 'kedaluwarsa' or (status = 'menunggu' and kedaluwarsa_at is not null and kedaluwarsa_at <= ?))",
            default => "(status = '".$status."')",
        };
    }

    /** @return list<string> ikatan untuk kondisi() */
    private static function ikatan(string $status): array
    {
        return in_array($status, ['menunggu', 'kedaluwarsa'], true) ? [now()->toDateTimeString()] : [];
    }

    /** @return Builder<Pesanan> */
    public function saring(Builder $q, string $status = '', string $cari = '', string $metode = '', string $periode = '', string $dari = '', string $sampai = ''): Builder
    {
        $this->terapkanDasar($q, $cari, $metode, $periode, $dari, $sampai);
        if (in_array($status, self::STATUS, true)) {
            $q->whereRaw(self::kondisi($status), self::ikatan($status));
        }

        return $q;
    }

    /** Semua filter selain status (dipakai juga untuk menghitung jumlah per status). */
    public function terapkanDasar(Builder $q, string $cari = '', string $metode = '', string $periode = '', string $dari = '', string $sampai = ''): Builder
    {
        $kata = addcslashes(mb_strtolower(trim($cari)), '\\%_');
        if ($kata !== '') {
            $q->where(function ($w) use ($kata) {
                $w->whereRaw("lower(nomor) like ? escape '\\'", ["%{$kata}%"])
                    ->orWhereRaw("lower(pembeli_nama) like ? escape '\\'", ["%{$kata}%"])
                    ->orWhereRaw("lower(pembeli_email) like ? escape '\\'", ["%{$kata}%"])
                    ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('item_pesanan')
                        ->whereColumn('item_pesanan.pesanan_id', 'pesanan.id')
                        ->whereRaw("lower(item_pesanan.nama) like ? escape '\\'", ["%{$kata}%"]));
            });
        }
        if ($metode !== '') {
            $q->where('metode_label', $metode);
        }
        [$awal, $akhir] = $this->rentang($periode, $dari, $sampai);
        if ($awal) {
            $q->where('created_at', '>=', $awal);
        }
        if ($akhir) {
            $q->where('created_at', '<=', $akhir);
        }

        return $q;
    }

    /** @return array{0:?Carbon,1:?Carbon} */
    public function rentang(string $periode, string $dari = '', string $sampai = ''): array
    {
        return match ($periode) {
            'today' => [now()->startOfDay(), null],
            '7' => [now()->subDays(7), null],
            '30' => [now()->subDays(30), null],
            'month' => [now()->startOfMonth(), null],
            'custom' => $this->rentangKustom($dari, $sampai),
            default => [null, null],
        };
    }

    /** Rentang terbalik dianggap tidak ada (halaman menampilkan galat). @return array{0:?Carbon,1:?Carbon} */
    private function rentangKustom(string $dari, string $sampai): array
    {
        $a = self::tanggal($dari);
        $b = self::tanggal($sampai);
        if ($a && $b && $a->gt($b)) {
            return [null, null];
        }

        return [$a?->startOfDay(), $b?->endOfDay()];
    }

    public static function tanggal(string $nilai): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
            return null;
        }
        try {
            $t = Carbon::createFromFormat('!Y-m-d', $nilai);
        } catch (\Throwable) {
            return null;
        }

        return $t && $t->format('Y-m-d') === $nilai ? $t : null;
    }

    public function urutkan(Builder $q, string $urut): Builder
    {
        return match ($urut) {
            'lama' => $q->orderBy('created_at')->orderBy('id'),
            'tinggi' => $q->orderByDesc('total')->orderByDesc('id'),
            'rendah' => $q->orderBy('total')->orderByDesc('id'),
            default => $q->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * Jumlah dan nilai per status efektif, dalam satu kueri.
     *
     * @return array{semua:array{n:int,nilai:int}, menunggu:array{n:int,nilai:int}, lunas:array{n:int,nilai:int}, kedaluwarsa:array{n:int,nilai:int}, batal:array{n:int,nilai:int}, refund:array{n:int,nilai:int}}
     */
    public function hitungPerStatus(Builder $q): array
    {
        $sel = ['count(*) as semua_n', 'coalesce(sum(total), 0) as semua_nilai'];
        $ikat = [];
        foreach (self::STATUS as $s) {
            $sel[] = 'coalesce(sum(case when '.self::kondisi($s).' then 1 else 0 end), 0) as '.$s.'_n';
            $sel[] = 'coalesce(sum(case when '.self::kondisi($s).' then total else 0 end), 0) as '.$s.'_nilai';
            array_push($ikat, ...self::ikatan($s), ...self::ikatan($s));
        }
        $r = $q->toBase()->selectRaw(implode(', ', $sel), $ikat)->first();

        $hasil = ['semua' => ['n' => (int) ($r->semua_n ?? 0), 'nilai' => (int) ($r->semua_nilai ?? 0)]];
        foreach (self::STATUS as $s) {
            $hasil[$s] = ['n' => (int) ($r->{$s.'_n'} ?? 0), 'nilai' => (int) ($r->{$s.'_nilai'} ?? 0)];
        }

        return $hasil;
    }

    /**
     * Angka ringkasan tanpa filter (kartu KPI).
     *
     * @return array<string,int>
     */
    public function kpi(): array
    {
        $kini = now();
        $hari = $kini->copy()->startOfDay()->toDateTimeString();
        $bulan = $kini->copy()->startOfMonth()->toDateTimeString();
        $sekarang = $kini->toDateTimeString();
        $menunggu = self::kondisi('menunggu');
        $sql = <<<SQL
            count(*) as semua,
            coalesce(sum(case when created_at >= ? then 1 else 0 end), 0) as hari_n,
            coalesce(sum(case when created_at >= ? and status = 'lunas' then total else 0 end), 0) as hari_lunas_nilai,
            coalesce(sum(case when created_at >= ? then 1 else 0 end), 0) as bulan_n,
            coalesce(sum(case when created_at >= ? and status = 'lunas' then 1 else 0 end), 0) as bulan_lunas_n,
            coalesce(sum(case when status = 'lunas' then total else 0 end), 0) as lunas_nilai,
            coalesce(sum(case when status = 'lunas' then 1 else 0 end), 0) as lunas_n,
            coalesce(sum(case when status = 'lunas' and dibayar_at >= ? then total else 0 end), 0) as lunas_bulan_nilai,
            coalesce(sum(case when status in ('lunas', 'refund') then 1 else 0 end), 0) as pernah_bayar,
            coalesce(sum(case when {$menunggu} then 1 else 0 end), 0) as menunggu_n,
            coalesce(sum(case when {$menunggu} then total else 0 end), 0) as menunggu_nilai,
            coalesce(sum(case when status <> 'batal' then total else 0 end), 0) as tertagih_nilai
            SQL;
        $r = Pesanan::query()->toBase()->selectRaw($sql, [$hari, $hari, $bulan, $bulan, $bulan, $sekarang, $sekarang])->first();

        return collect((array) $r)->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Pendapatan lunas per bulan untuk $bulan bulan terakhir (termasuk bulan ini), terlama dulu. Satu kueri dengan
     * jumlah bersyarat per bulan, tanpa fungsi tanggal khusus basis data.
     *
     * @return list<array{bulan:Carbon,nilai:int,jumlah:int}>
     */
    public function pendapatanBulanan(int $bulan = 6): array
    {
        $awalBulan = now()->startOfMonth();
        $rentang = [];
        $sel = [];
        $ikat = [];
        for ($i = $bulan - 1; $i >= 0; $i--) {
            $a = $awalBulan->copy()->subMonthsNoOverflow($i);
            $b = $a->copy()->addMonthNoOverflow();
            $k = $bulan - 1 - $i;
            $rentang[] = $a;
            $sel[] = "coalesce(sum(case when dibayar_at >= ? and dibayar_at < ? then total else 0 end), 0) as nilai_{$k}";
            $sel[] = "coalesce(sum(case when dibayar_at >= ? and dibayar_at < ? then 1 else 0 end), 0) as jumlah_{$k}";
            array_push($ikat, $a->toDateTimeString(), $b->toDateTimeString(), $a->toDateTimeString(), $b->toDateTimeString());
        }
        $r = Pesanan::query()->where('status', Pesanan::LUNAS)->toBase()->selectRaw(implode(', ', $sel), $ikat)->first();

        return collect($rentang)->map(fn (Carbon $a, int $k) => [
            'bulan' => $a,
            'nilai' => (int) ($r->{'nilai_'.$k} ?? 0),
            'jumlah' => (int) ($r->{'jumlah_'.$k} ?? 0),
        ])->all();
    }
}
