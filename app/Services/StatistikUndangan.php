<?php

namespace App\Services;

use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Ucapan;
use App\Models\Undangan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Angka statistik satu undangan. Semua penjumlahan dikerjakan basis data (GROUP BY / SUM), bukan dengan memuat baris;
 * hanya data yang memang tercatat: waktu tayangan, tamu yang membuka (tamu_id), RSVP, ucapan, dan tanda terkirim.
 * Waktu tersimpan dalam zona aplikasi (Asia/Jakarta), jadi jam/hari dibaca langsung dari kolom tanpa konversi.
 */
class StatistikUndangan
{
    public const RENTANG = [7, 30, 90];

    private const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const HARI_PENUH = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Awal (termasuk) dan akhir (tidak termasuk) rentang yang berakhir hari ini. @return array{0:Carbon,1:Carbon} */
    public function batas(int $hari): array
    {
        $akhir = now()->startOfDay()->addDay();

        return [$akhir->copy()->subDays($hari), $akhir];
    }

    /**
     * @return array{
     *   rentang:int, awal:Carbon, akhir:Carbon,
     *   kunjungan:array{total:int,sebelumnya:int,delta:?int,buckets:list<array>,puncak:int},
     *   jam:list<int>, hari:list<int>,
     *   tamu:array{total:int,terkirim:int,dilihat:int,rsvp:int,hadir:int,alur:list<int>},
     *   rsvp:array{total:int,hadir:int,tidak:int}, ucapan:int
     * }
     */
    public function hitung(Undangan $u, int $hari): array
    {
        $hari = in_array($hari, self::RENTANG, true) ? $hari : 7;
        [$awal, $akhir] = $this->batas($hari);
        $sebelum = $awal->copy()->subDays($hari);

        $dasar = fn () => Tayangan::query()->where('undangan_id', $u->id)->toBase();

        $total = $dasar()->where('created_at', '>=', $awal)->where('created_at', '<', $akhir)->count();
        $lalu = $dasar()->where('created_at', '>=', $sebelum)->where('created_at', '<', $awal)->count();

        $perHari = $dasar()->where('created_at', '>=', $awal)->where('created_at', '<', $akhir)
            ->selectRaw('date(created_at) as hari, count(*) as n')->groupByRaw('date(created_at)')->pluck('n', 'hari')
            ->mapWithKeys(fn ($n, $h) => [Carbon::parse((string) $h)->format('Y-m-d') => (int) $n])->all();

        $jam = array_fill(0, 24, 0);
        foreach ($dasar()->where('created_at', '>=', $awal)->where('created_at', '<', $akhir)
            ->selectRaw($this->ekspresi('jam').' as k, count(*) as n')->groupByRaw($this->ekspresi('jam'))->pluck('n', 'k') as $k => $n) {
            $jam[(int) $k] = (int) $n;
        }

        // Senin = indeks 0 (kolom basis data: 0 = Minggu).
        $minggu = array_fill(0, 7, 0);
        foreach ($dasar()->where('created_at', '>=', $awal)->where('created_at', '<', $akhir)
            ->selectRaw($this->ekspresi('hari').' as k, count(*) as n')->groupByRaw($this->ekspresi('hari'))->pluck('n', 'k') as $k => $n) {
            $minggu[((int) $k + 6) % 7] = (int) $n;
        }

        $rsvp = Rsvp::query()->where('undangan_id', $u->id)->toBase()
            ->selectRaw("count(*) as total, sum(case when status = 'hadir' then 1 else 0 end) as hadir, sum(case when status = 'tidak' then 1 else 0 end) as tidak")->first();

        return [
            'rentang' => $hari, 'awal' => $awal, 'akhir' => $akhir,
            'kunjungan' => [
                'total' => $total, 'sebelumnya' => $lalu,
                'delta' => $lalu > 0 ? (int) round(($total - $lalu) / $lalu * 100) : null,
                'buckets' => $this->bucket($hari, $awal, $perHari),
            ],
            'jam' => $jam, 'hari' => $minggu,
            'tamu' => $this->tamu($u),
            'rsvp' => ['total' => (int) ($rsvp->total ?? 0), 'hadir' => (int) ($rsvp->hadir ?? 0), 'tidak' => (int) ($rsvp->tidak ?? 0)],
            'ucapan' => Ucapan::query()->where('undangan_id', $u->id)->count(),
        ];
    }

    /** Satu kueri atas tamu: terdaftar, terkirim, dilihat (ada tayangan atau RSVP), lalu alur dari yang terkirim. */
    public function tamu(Undangan $u): array
    {
        $r = DB::selectOne(<<<'SQL'
            select count(*) as total,
                   coalesce(sum(terkirim), 0) as terkirim,
                   coalesce(sum(dilihat), 0) as dilihat,
                   coalesce(sum(terkirim * dilihat), 0) as kirim_lihat,
                   coalesce(sum(terkirim * rsvp), 0) as kirim_rsvp,
                   coalesce(sum(terkirim * hadir), 0) as kirim_hadir
            from (
                select case when t.dikirim_at is not null then 1 else 0 end as terkirim,
                       case when exists (select 1 from tayangan y where y.tamu_id = t.id) or exists (select 1 from rsvp r where r.tamu_id = t.id) then 1 else 0 end as dilihat,
                       case when exists (select 1 from rsvp r where r.tamu_id = t.id) then 1 else 0 end as rsvp,
                       case when exists (select 1 from rsvp r where r.tamu_id = t.id and r.status = 'hadir') then 1 else 0 end as hadir
                from tamu t where t.undangan_id = ?
            ) x
            SQL, [$u->id]);

        return [
            'total' => (int) $r->total, 'terkirim' => (int) $r->terkirim, 'dilihat' => (int) $r->dilihat,
            'rsvp' => (int) $r->kirim_rsvp, 'hadir' => (int) $r->kirim_hadir,
            // Alur: terkirim -> dilihat -> sudah RSVP -> hadir, hanya dari tamu yang sudah ditandai terkirim.
            'alur' => [(int) $r->terkirim, (int) $r->kirim_lihat, (int) $r->kirim_rsvp, (int) $r->kirim_hadir],
        ];
    }

    /** Tamu yang belum membuka (tanpa tayangan dan tanpa RSVP): yang sudah dikirim dulu, terlama dulu. */
    public function belumMembuka(Undangan $u, int $batas): array
    {
        $kueri = Tamu::query()->where('undangan_id', $u->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tayangan')->whereColumn('tayangan.tamu_id', 'tamu.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('rsvp')->whereColumn('rsvp.tamu_id', 'tamu.id'));

        $jumlah = (clone $kueri)->count();
        $daftar = $kueri->orderByRaw('case when dikirim_at is null then 1 else 0 end')->orderBy('dikirim_at')->orderBy('id')->limit($batas)->get();

        return ['jumlah' => $jumlah, 'daftar' => $daftar];
    }

    /** @param array<string,int> $perHari  Y-m-d => jumlah */
    private function bucket(int $hari, Carbon $awal, array $perHari): array
    {
        $hasil = [];
        if ($hari === 90) {
            for ($i = 0; $i < 13; $i++) {
                $b0 = $awal->copy()->addDays($i * 7);
                $n = 0;
                for ($d = 0; $d < 7; $d++) {
                    $n += $perHari[$b0->copy()->addDays($d)->format('Y-m-d')] ?? 0;
                }
                $hasil[] = [
                    'label' => $i % 2 === 0 ? $this->tglPendek($b0) : '', 'sub' => '', 'nilai' => $n,
                    'penuh' => $this->tglPendek($b0).' sampai '.$this->tglLengkap($b0->copy()->addDays(6)),
                ];
            }

            return $hasil;
        }

        for ($i = 0; $i < $hari; $i++) {
            $t = $awal->copy()->addDays($i);
            $tampil = $hari === 7 || (($hari - 1 - $i) % 5 === 0);
            $hasil[] = [
                'label' => $hari === 7 ? self::HARI[$t->dayOfWeek] : ($tampil ? (string) $t->day : ''),
                'sub' => $hari === 7 ? $this->tglPendek($t) : ($tampil ? self::BULAN[$t->month - 1] : ''),
                'nilai' => $perHari[$t->format('Y-m-d')] ?? 0,
                'penuh' => self::HARI_PENUH[$t->dayOfWeek].', '.$this->tglLengkap($t),
            ];
        }

        return $hasil;
    }

    public function tglPendek(Carbon $t): string
    {
        return $t->day.' '.self::BULAN[$t->month - 1];
    }

    public function tglLengkap(Carbon $t): string
    {
        return $this->tglPendek($t).' '.$t->year;
    }

    /** Ekspresi tanggal-waktu per mesin basis data (SQLite untuk tes, PostgreSQL untuk produksi). */
    private function ekspresi(string $apa): string
    {
        $pg = DB::connection()->getDriverName() === 'pgsql';

        return match ($apa) {
            'jam' => $pg ? 'extract(hour from created_at)::int' : "cast(strftime('%H', created_at) as integer)",
            'hari' => $pg ? 'extract(dow from created_at)::int' : "cast(strftime('%w', created_at) as integer)",
        };
    }
}
