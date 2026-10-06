<?php

namespace App\Livewire;

use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Ucapan;
use App\Models\Undangan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Beranda extends Component
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    /** semua | terbit | draf */
    #[Url(as: 'status', except: 'semua')]
    public string $filter = 'semua';

    /** Undangan yang disorot di bagian atas; divalidasi terhadap pemilik setiap render. */
    #[Url(as: 'u', except: '')]
    public ?int $utama = null;

    /** Data berat baru dimuat setelah halaman tampil, supaya kerangka pemuat terlihat. */
    public bool $siap = false;

    public bool $contoh = false;

    /** Dikunci: klien tidak boleh menentukan id yang dihapus/dibagikan; pemilik tetap dicek di tiap aksi. */
    #[Locked]
    public ?int $hapusId = null;

    public bool $tampilHapus = false;

    #[Locked]
    public ?int $bagikanId = null;

    public bool $tampilBagikan = false;

    public function muat(): void
    {
        $this->siap = true;
    }

    public function pilihFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['semua', 'terbit', 'draf'], true) ? $filter : 'semua';
    }

    public function tampilkanContoh(): void
    {
        $this->contoh = true;
    }

    public function sembunyikanContoh(): void
    {
        $this->contoh = false;
        $this->tampilBagikan = false;
        $this->tampilHapus = false;
    }

    public function jadikanUtama(int $id): void
    {
        $this->milik($id);
        $this->utama = $id;
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->hapusId = $this->milik($id)->id;
        $this->tampilHapus = true;
    }

    public function hapus(): void
    {
        $undangan = $this->hapusId ? Undangan::query()->where('user_id', auth()->id())->find($this->hapusId) : null;
        $this->tampilHapus = false;
        $this->hapusId = null;

        if (! $undangan) {
            return;
        }

        $judul = $undangan->judul;
        $undangan->delete();
        if ($this->utama === $undangan->id) {
            $this->utama = null;
        }
        $this->dispatch('toast', pesan: "Undangan \"{$judul}\" dihapus.", tipe: 'sukses');
    }

    public function bagikan(int $id): void
    {
        $this->bagikanId = $this->milik($id)->id;
        $this->tampilBagikan = true;
    }

    private function milik(int $id): Undangan
    {
        $undangan = Undangan::query()->where('user_id', auth()->id())->find($id);
        abort_unless($undangan, 404);

        return $undangan;
    }

    public function render()
    {
        $pengguna = auth()->user();
        $jam = (int) now()->format('G');
        $sapaan = match (true) {
            $jam < 11 => 'Selamat pagi',
            $jam < 15 => 'Selamat siang',
            $jam < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        $data = null;
        $adaUndangan = false;
        if ($this->siap) {
            $data = $this->muatNyata($pengguna->id);
            $adaUndangan = $data !== null;
            if (! $adaUndangan && $this->contoh) {
                $data = $this->dataContoh();
            }
        }

        $bagikan = null;
        if ($data && ! $data['contoh'] && $this->bagikanId) {
            $bagikan = collect($data['daftar'])->firstWhere('id', $this->bagikanId);
        }
        $hapus = null;
        if ($data && ! $data['contoh'] && $this->hapusId) {
            $hapus = collect($data['daftar'])->firstWhere('id', $this->hapusId);
        }

        return view('livewire.beranda', [
            'siap' => $this->siap,
            'data' => $data,
            'modeContoh' => $data['contoh'] ?? false,
            'bagikan' => $bagikan,
            'hapus' => $hapus,
            'tautanBuat' => $this->tautan('buat-undangan'),
        ])->layout('layouts::app', [
            'judul' => "{$sapaan}, {$pengguna->sapaan()}",
            'sub' => 'Ini ringkasan undanganmu hari ini.',
        ]);
    }

    /** Tautan ke rute bernama bila ada; jatuh ke '#' agar halaman tetap jalan. */
    public function tautan(string $nama, array $param = []): string
    {
        return Route::has($nama) ? route($nama, $param) : '#';
    }

    /** @return array<string,mixed>|null null bila pengguna belum punya undangan */
    private function muatNyata(int $userId): ?array
    {
        /** @var Collection<int,Undangan> $semua */
        $semua = Undangan::query()
            ->where('user_id', $userId)
            ->withCount(['tamu', 'tayangan', 'rsvp as hadir_count' => fn ($q) => $q->where('status', Rsvp::HADIR)])
            ->latest('updated_at')->latest('id')
            ->get();

        if ($semua->isEmpty()) {
            return null;
        }

        $utama = ($this->utama ? $semua->firstWhere('id', $this->utama) : null) ?? $semua->first();
        $ids = $semua->pluck('id');
        $hariIni = now()->startOfDay();

        $daftar = $semua->map(fn (Undangan $u) => [
            'id' => $u->id,
            'judul' => $u->judul,
            'mono' => $this->monogram($u->judul),
            'jenis' => $u->jenis,
            'slug' => $u->slug,
            'link' => $u->slug ? $this->hostPublik().'/'.$u->slug : null,
            'tautanTamu' => $u->sudahTerbit() && $u->slug ? url('/'.$u->slug) : null,
            'status' => $u->sudahTerbit() ? 'terbit' : 'draf',
            'tanggal' => $this->tanggal($u->tanggal_acara),
            'tamu' => $u->tamu_count,
            'dilihat' => $u->tayangan_count,
            'hadir' => $u->hadir_count,
            'perubahan' => $u->sudahTerbit() && $u->ada_perubahan,
            'terbit' => $u->sudahTerbit() ? ($u->diterbitkan_at ? 'Terbit '.$this->waktuRelatif($u->diterbitkan_at) : 'Sudah terbit') : 'Belum diterbitkan',
            'utama' => $u->id === $utama->id,
        ])->all();

        $kpi = [
            ['label' => 'Undangan', 'nilai' => $semua->count(), 'delta' => $semua->filter(fn ($u) => $u->sudahTerbit())->count().' terbit', 'ikon' => 'beranda-undangan'],
            ['label' => 'Total tamu', 'nilai' => $semua->sum('tamu_count'), 'delta' => '+'.Tamu::query()->whereIn('undangan_id', $ids)->where('created_at', '>=', $hariIni)->count().' hari ini', 'ikon' => 'beranda-tamu'],
            ['label' => 'Konfirmasi hadir', 'nilai' => $semua->sum('hadir_count'), 'delta' => '+'.Rsvp::query()->whereIn('undangan_id', $ids)->where('status', Rsvp::HADIR)->where('created_at', '>=', $hariIni)->count().' hari ini', 'ikon' => 'rsvp'],
            ['label' => 'Tayangan', 'nilai' => $semua->sum('tayangan_count'), 'delta' => '+'.Tayangan::query()->whereIn('undangan_id', $ids)->where('created_at', '>=', $hariIni)->count().' hari ini', 'ikon' => 'beranda-lihat'],
        ];

        $rsvpPerStatus = Rsvp::query()->where('undangan_id', $utama->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $hadir = (int) ($rsvpPerStatus[Rsvp::HADIR] ?? 0);
        $tidak = (int) ($rsvpPerStatus[Rsvp::TIDAK] ?? 0);
        $tamu = $utama->tamu_count;
        $terkirim = Tamu::query()->where('undangan_id', $utama->id)->whereNotNull('dikirim_at')->count();
        $belum = max(0, $tamu - $hadir - $tidak);

        $hero = $this->heroDari($utama, $daftar, $hadir, $tamu);

        $langkah = [
            ['label' => 'Pilih tema', 'hint' => 'Tema dipilih di Edit Undangan', 'ok' => $utama->tema_id !== null],
            ['label' => 'Isi data undangan', 'hint' => 'Nama mempelai, tanggal, lokasi', 'ok' => ! empty($utama->isi) && $utama->tanggal_acara !== null],
            ['label' => 'Tambah daftar tamu', 'hint' => 'Supaya tiap tamu punya tautan sendiri', 'ok' => $tamu > 0],
            ['label' => 'Terbitkan undangan', 'hint' => 'Tautan siap dibagikan', 'ok' => $utama->sudahTerbit()],
        ];
        $selesai = collect($langkah)->where('ok', true)->count();
        $berikut = collect($langkah)->search(fn ($l) => ! $l['ok']);
        foreach ($langkah as $i => &$l) {
            $l['no'] = $i + 1;
            $l['href'] = $i === 2 ? $this->tautan('bagikan') : $this->tautan('edit-undangan', ['undangan' => $utama->id]);
            $l['sekarang'] = $berikut === $i;
        }
        unset($l);

        $totalRsvp = $hadir + $tidak + $belum;
        $persen = fn (int $n) => $totalRsvp > 0 ? (int) round($n / $totalRsvp * 100) : 0;

        return [
            'contoh' => false,
            'daftar' => $daftar,
            'hitung' => ['semua' => count($daftar), 'terbit' => collect($daftar)->where('status', 'terbit')->count(), 'draf' => collect($daftar)->where('status', 'draf')->count()],
            'hero' => $hero,
            'kpi' => $kpi,
            'langkah' => ['tampil' => $selesai < 4, 'selesai' => $selesai, 'item' => $langkah],
            'kunjungan' => $this->kunjungan($utama->id),
            'rsvp' => [
                'terkirim' => $terkirim, 'pct' => $tamu > 0 ? (int) round($hadir / $tamu * 100) : 0,
                'hadir' => $hadir, 'tidak' => $tidak, 'belum' => $belum,
                'pHadir' => $persen($hadir), 'pTidak' => $persen($tidak), 'pBelum' => $persen($belum),
                'donat' => $totalRsvp > 0
                    ? 'conic-gradient(#16A34A 0 '.($hadir / $totalRsvp * 360).'deg, #EF4444 0 '.(($hadir + $tidak) / $totalRsvp * 360).'deg, #F59E0B 0 360deg)'
                    : '#EEF0F3',
            ],
            'aktivitas' => $this->aktivitas($utama->id),
            'ucapan' => Ucapan::query()->where('undangan_id', $utama->id)->latest()->latest('id')->limit(2)->get()
                ->map(fn ($u) => ['nama' => $u->nama, 'waktu' => $this->waktuRelatif($u->created_at), 'pesan' => $u->pesan])->all(),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $daftar
     * @return array<string,mixed>
     */
    private function heroDari(Undangan $u, array $daftar, int $hadir, int $tamu): array
    {
        $baris = collect($daftar)->firstWhere('id', $u->id);
        $jamAcara = $u->tanggal_acara?->copy()->setTime(9, 0);

        return $baris + [
            'tempat' => $u->lokasi_ringkas ?: '–',
            'hadirPersen' => $tamu > 0 ? (int) round($hadir / $tamu * 100) : 0,
            'jumlahHadir' => $hadir,
            'jumlahTamu' => $tamu,
            'target' => $jamAcara?->toIso8601String(),
            'tanggalKosong' => $u->tanggal_acara === null,
        ];
    }

    /** @return array{total:int,tren:string,naik:bool,bar:list<array{v:int,hari:string,h:int,puncak:bool}>} */
    private function kunjungan(int $undanganId): array
    {
        $mulai = now()->startOfDay()->subDays(6);
        $baris = Tayangan::query()->where('undangan_id', $undanganId)->where('created_at', '>=', $mulai)
            ->selectRaw('date(created_at) as hari, count(*) as n')->groupBy('hari')->pluck('n', 'hari');

        $nilai = [];
        for ($i = 0; $i < 7; $i++) {
            $hari = $mulai->copy()->addDays($i);
            $nilai[] = ['v' => (int) ($baris[$hari->toDateString()] ?? 0), 'hari' => self::HARI[$hari->dayOfWeek]];
        }

        $total = array_sum(array_column($nilai, 'v'));
        $maks = max(1, ...array_column($nilai, 'v'));
        $sebelumnya = Tayangan::query()->where('undangan_id', $undanganId)
            ->where('created_at', '>=', $mulai->copy()->subDays(7))->where('created_at', '<', $mulai)->count();

        $tren = match (true) {
            $total === 0 => 'Belum ada kunjungan',
            $sebelumnya === 0 => 'Baru minggu ini',
            default => ($total >= $sebelumnya ? '+' : '-').abs((int) round(($total - $sebelumnya) / $sebelumnya * 100)).'% dari minggu lalu',
        };

        return [
            'total' => $total,
            'tren' => $tren,
            'naik' => $total > 0 && ($sebelumnya === 0 || $total >= $sebelumnya),
            'bar' => array_map(fn ($b) => $b + [
                'h' => $b['v'] === 0 ? 4 : (int) round($b['v'] / $maks * 140),
                'puncak' => $total > 0 && $b['v'] === $maks,
            ], $nilai),
        ];
    }

    /** @return list<array{ini:string,nama:string,info:string,jenis:string,waktu:string}> */
    private function aktivitas(int $undanganId): array
    {
        $rsvp = Rsvp::query()->where('undangan_id', $undanganId)->latest()->latest('id')->limit(5)->get()->map(fn ($r) => [
            'at' => $r->created_at,
            'nama' => $r->nama,
            'info' => $r->status === Rsvp::HADIR ? 'Mengonfirmasi hadir · '.$r->jumlah.' orang' : 'Berhalangan hadir',
            'jenis' => $r->status === Rsvp::HADIR ? 'hadir' : 'tidak',
        ]);

        $tayangan = Tayangan::query()->where('undangan_id', $undanganId)->whereNotNull('tamu_id')
            ->latest('created_at')->latest('id')->limit(5)->get();
        $namaTamu = Tamu::query()->where('undangan_id', $undanganId)->whereIn('id', $tayangan->pluck('tamu_id'))->pluck('nama', 'id');
        $dilihat = $tayangan->filter(fn ($t) => isset($namaTamu[$t->tamu_id]))->map(fn ($t) => [
            'at' => $t->created_at,
            'nama' => $namaTamu[$t->tamu_id],
            'info' => 'Membuka undangan',
            'jenis' => 'dilihat',
        ]);

        return $rsvp->concat($dilihat)->sortByDesc('at')->take(5)->map(fn ($a) => [
            'ini' => $this->inisial($a['nama']),
            'nama' => $a['nama'],
            'info' => $a['info'],
            'jenis' => $a['jenis'],
            'waktu' => $this->waktuRelatif($a['at']),
        ])->values()->all();
    }

    /** Data ilustrasi untuk akun yang belum punya undangan; tidak disimpan dan tidak bisa diubah. */
    private function dataContoh(): array
    {
        $baris = fn (string $judul, string $status, int $tamu, int $dilihat, int $hadir, bool $utama) => [
            'id' => null, 'judul' => $judul, 'mono' => $this->monogram($judul), 'jenis' => 'Pernikahan', 'slug' => Str::slug($judul),
            'link' => $this->hostPublik().'/'.Str::slug($judul), 'tautanTamu' => null, 'status' => $status,
            'tanggal' => $status === 'terbit' ? '14 Des 2026' : '–', 'tamu' => $tamu, 'dilihat' => $dilihat, 'hadir' => $hadir,
            'perubahan' => false, 'terbit' => $status === 'terbit' ? 'Terbit 3 hari lalu' : 'Belum diterbitkan', 'utama' => $utama,
        ];
        $daftar = [$baris('Raka & Sinta', 'terbit', 120, 342, 68, true), $baris('Dimas & Laras', 'draf', 0, 0, 0, false)];

        return [
            'contoh' => true,
            'daftar' => $daftar,
            'hitung' => ['semua' => 2, 'terbit' => 1, 'draf' => 1],
            'hero' => $daftar[0] + [
                'tempat' => 'Gedung Sasana Kriya, Jakarta', 'hadirPersen' => 57, 'jumlahHadir' => 68, 'jumlahTamu' => 120,
                'target' => now()->addDays(68)->setTime(9, 0)->toIso8601String(), 'tanggalKosong' => false,
            ],
            'kpi' => [
                ['label' => 'Undangan', 'nilai' => 2, 'delta' => '1 terbit', 'ikon' => 'beranda-undangan'],
                ['label' => 'Total tamu', 'nilai' => 120, 'delta' => '+4 hari ini', 'ikon' => 'beranda-tamu'],
                ['label' => 'Konfirmasi hadir', 'nilai' => 68, 'delta' => '+6 hari ini', 'ikon' => 'rsvp'],
                ['label' => 'Tayangan', 'nilai' => 342, 'delta' => '+31 hari ini', 'ikon' => 'beranda-lihat'],
            ],
            'langkah' => ['tampil' => false, 'selesai' => 4, 'item' => []],
            'kunjungan' => [
                'total' => 259, 'tren' => '+18% dari minggu lalu', 'naik' => true,
                'bar' => collect([18, 31, 27, 52, 44, 61, 26])->map(fn ($v, $i) => [
                    'v' => $v, 'hari' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'][$i], 'h' => (int) round($v / 61 * 140), 'puncak' => $v === 61,
                ])->all(),
            ],
            'rsvp' => ['terkirim' => 98, 'pct' => 57, 'hadir' => 68, 'tidak' => 12, 'belum' => 40, 'pHadir' => 57, 'pTidak' => 10, 'pBelum' => 33,
                'donat' => 'conic-gradient(#16A34A 0 204deg, #EF4444 0 240deg, #F59E0B 0 360deg)'],
            'aktivitas' => [
                ['ini' => 'BS', 'nama' => 'Budi Santoso', 'info' => 'Mengonfirmasi hadir · 2 orang', 'jenis' => 'hadir', 'waktu' => '5 mnt lalu'],
                ['ini' => 'KW', 'nama' => 'Keluarga Wijaya', 'info' => 'Membuka undangan', 'jenis' => 'dilihat', 'waktu' => '18 mnt lalu'],
                ['ini' => 'AL', 'nama' => 'Ayu Lestari', 'info' => 'Berhalangan hadir', 'jenis' => 'tidak', 'waktu' => '1 jam lalu'],
            ],
            'ucapan' => [
                ['nama' => 'Nadia & Keluarga', 'waktu' => 'Hari ini', 'pesan' => 'Selamat menempuh hidup baru! Semoga selalu bahagia dan dimudahkan dalam segala hal.'],
                ['nama' => 'Om Hendra', 'waktu' => 'Kemarin', 'pesan' => 'Selamat ya! Kami sekeluarga pasti hadir. Semoga lancar sampai hari H.'],
            ],
        ];
    }

    private function hostPublik(): string
    {
        return parse_url(url('/'), PHP_URL_HOST) ?: 'undangyah.com';
    }

    private function monogram(string $judul): string
    {
        $kata = preg_split('/[\s&]+/u', trim($judul), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(collect($kata)->map(fn ($k) => mb_substr($k, 0, 1))->take(2)->implode(''));
    }

    private function inisial(string $nama): string
    {
        return $this->monogram($nama) ?: '?';
    }

    private function tanggal(?Carbon $t): string
    {
        return $t ? $t->format('j').' '.self::BULAN[$t->month - 1].' '.$t->year : '–';
    }

    private function waktuRelatif(Carbon $t): string
    {
        $detik = max(0, (int) $t->diffInSeconds(now(), false));

        return match (true) {
            $detik >= 7 * 86400 => $this->tanggal($t),
            $detik < 60 => 'baru saja',
            $detik < 3600 => intdiv($detik, 60).' mnt lalu',
            $detik < 86400 => intdiv($detik, 3600).' jam lalu',
            default => intdiv($detik, 86400).' hari lalu',
        };
    }
}
