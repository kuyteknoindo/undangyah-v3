<?php

namespace App\Livewire;

use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Services\KodeQr;
use App\Services\PesanUndangan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Beranda = dashboard penuh untuk SEMUA akun (papan Main.dc.html). Akun tanpa undangan melihat dashboard yang sama
 * dengan keadaan kosong per widget; onboarding hanya blok kecil "Langkah menuju undangan siap".
 */
class Beranda extends Component
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const FILTER = ['semua', 'utama', 'aktif', 'draf'];

    /** semua | utama | aktif | draf */
    #[Url(as: 'status', except: 'semua')]
    public string $filter = 'semua';

    /** Undangan yang disorot di bagian atas; divalidasi terhadap pemilik setiap render. */
    #[Url(as: 'u', except: '')]
    public ?int $utama = null;

    /** Kolom cari di header: menyaring tabel "Undangan saya" (judul, tautan, jenis, atau nama tamu). */
    #[Url(as: 'cari', except: '')]
    public string $cari = '';

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
        $filter = $filter === 'terbit' ? 'aktif' : $filter;
        $this->filter = in_array($filter, self::FILTER, true) ? $filter : 'semua';
    }

    public function hapusCari(): void
    {
        $this->cari = '';
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
        if ($this->siap) {
            $data = $this->muatNyata($pengguna->id);
            if ($data['kosong'] && $this->contoh) {
                $data = $this->dataContoh();
            }
        }

        $bagikan = null;
        if ($data && ! $data['contoh'] && $this->bagikanId) {
            $bagikan = $this->dataBagikan(collect($data['daftar'])->firstWhere('id', $this->bagikanId));
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
            'cari' => true,
        ]);
    }

    /** Tautan ke rute bernama bila ada; jatuh ke '#' agar halaman tetap jalan. */
    public function tautan(string $nama, array $param = []): string
    {
        return Route::has($nama) ? route($nama, $param) : '#';
    }

    /**
     * Selalu mengembalikan data dashboard. `kosong` = akun belum punya undangan (semua widget memakai keadaan kosong).
     *
     * @return array<string,mixed>
     */
    private function muatNyata(int $userId): array
    {
        /** @var Collection<int,Undangan> $semua */
        // Kolom JSON besar (terbitan, bagian, pengaturan, langkah) tidak dipakai Beranda dan ikut terbaca ulang di setiap
        // aksi Livewire; `isi` hanya dibaca untuk undangan utama (lihat `$langkah`).
        $semua = Undangan::query()
            ->select(['id', 'user_id', 'tema_id', 'judul', 'jenis', 'slug', 'status', 'diterbitkan_at', 'ada_perubahan', 'tanggal_acara', 'lokasi_ringkas', 'created_at', 'updated_at'])
            ->where('user_id', $userId)
            ->withCount(['tamu', 'rsvp as hadir_count' => fn ($q) => $q->where('status', Rsvp::HADIR)])
            ->latest('updated_at')->latest('id')
            ->get();

        if ($semua->isEmpty()) {
            return $this->dataKosong();
        }

        $utama = ($this->utama ? $semua->firstWhere('id', $this->utama) : null) ?? $semua->first();
        $ids = $semua->pluck('id');
        $kemarin = now()->subDay();

        // "Dilihat" = tamu unik yang membuka + kunjungan tanpa nama (papan); satu kueri untuk semua undangan.
        $lihat = Tayangan::query()->whereIn('undangan_id', $ids)
            ->selectRaw('undangan_id, count(distinct tamu_id) as unik, sum(case when tamu_id is null then 1 else 0 end) as anon, sum(case when created_at >= ? then 1 else 0 end) as baru', [$kemarin])
            ->groupBy('undangan_id')->get()->keyBy('undangan_id');
        $dilihat = fn (int $id): int => (int) ($lihat[$id]->unik ?? 0) + (int) ($lihat[$id]->anon ?? 0);

        $daftar = $semua->map(fn (Undangan $u) => [
            'id' => $u->id,
            'judul' => $u->judul,
            'mono' => $this->monogram($u->judul),
            'jenis' => $u->jenis,
            'slug' => $u->slug,
            'link' => $u->slug ? $this->hostPublik().'/'.$u->slug : null,
            'tautanTamu' => $u->sudahTerbit() && $u->slug ? PesanUndangan::linkUndangan($u) : null,
            'status' => $u->sudahTerbit() ? 'aktif' : 'draf',
            'tanggal' => $this->tanggal($u->tanggal_acara),
            'tamu' => $u->tamu_count,
            'dilihat' => $dilihat($u->id),
            'hadir' => $u->hadir_count,
            'perubahan' => $u->sudahTerbit() && $u->ada_perubahan,
            'terbit' => $u->sudahTerbit() ? ($u->diterbitkan_at ? 'Terbit '.$this->waktuRelatif($u->diterbitkan_at) : 'Sudah terbit') : 'Belum diterbitkan',
            'utama' => $u->id === $utama->id,
        ])->all();

        $rsvpPerStatus = Rsvp::query()->where('undangan_id', $utama->id)
            ->selectRaw('status, count(*) as n, sum(case when created_at >= ? then 1 else 0 end) as baru', [$kemarin])
            ->groupBy('status')->get()->keyBy('status');
        $hadir = (int) ($rsvpPerStatus[Rsvp::HADIR]->n ?? 0);
        $tidak = (int) ($rsvpPerStatus[Rsvp::TIDAK]->n ?? 0);
        $hadirBaru = (int) ($rsvpPerStatus[Rsvp::HADIR]->baru ?? 0);
        $tamu = $utama->tamu_count;
        $ringkasTamu = Tamu::query()->where('undangan_id', $utama->id)
            ->selectRaw('sum(case when dikirim_at is not null then 1 else 0 end) as terkirim, sum(case when created_at >= ? then 1 else 0 end) as baru, sum(case when dikirim_at >= ? then 1 else 0 end) as terkirim_baru', [$kemarin, $kemarin])
            ->first();
        $terkirim = (int) ($ringkasTamu->terkirim ?? 0);
        $belum = max(0, $tamu - $hadir - $tidak);

        $stats = $this->statistik($tamu, (int) ($ringkasTamu->baru ?? 0), $terkirim, (int) ($ringkasTamu->terkirim_baru ?? 0), $dilihat($utama->id), (int) ($lihat[$utama->id]->baru ?? 0), $hadir, $hadirBaru);

        $langkah = $this->langkah([
            $utama->tema_id !== null,
            $utama->tanggal_acara !== null && $this->adaIsi($utama->id),
            $tamu > 0,
            $utama->sudahTerbit(),
        ], $utama->id);

        $baris = collect($daftar)->firstWhere('id', $utama->id);
        $hero = $baris + [
            'tempat' => $utama->lokasi_ringkas ?: '–',
            'hadirPersen' => $tamu > 0 ? (int) round($hadir / $tamu * 100) : 0,
            'jumlahHadir' => $hadir,
            'jumlahTamu' => $tamu,
            'target' => $utama->tanggal_acara?->copy()->setTime(10, 0)->toIso8601String(),
            'kosong' => false,
        ];

        return [
            'kosong' => false,
            'contoh' => false,
            'daftar' => $this->saring($daftar),
            'hitung' => $this->hitung($daftar),
            'hero' => $hero,
            'stats' => $stats,
            'langkah' => $langkah,
            'kunjungan' => $this->kunjungan($utama->id),
            'rsvp' => $this->ringkasRsvp($terkirim, $hadir, $tidak, $belum),
            'aktivitas' => $this->aktivitas($utama->id),
            'ucapan' => Ucapan::query()->where('undangan_id', $utama->id)->latest()->latest('id')->limit(2)->get()
                ->map(fn ($u) => ['nama' => $u->nama, 'waktu' => $this->waktuRelatif($u->created_at), 'pesan' => $u->pesan])->all(),
        ];
    }

    /** Akun tanpa undangan: dashboard dengan semua angka nol dan blok onboarding di dalamnya. */
    private function dataKosong(): array
    {
        return [
            'kosong' => true,
            'contoh' => false,
            'daftar' => [],
            'hitung' => ['semua' => 0, 'utama' => 0, 'aktif' => 0, 'draf' => 0],
            'hero' => ['kosong' => true, 'id' => null, 'target' => null],
            'stats' => $this->statistik(0, 0, 0, 0, 0, 0, 0, 0),
            'langkah' => $this->langkah([false, false, false, false], null),
            'kunjungan' => $this->kunjungan(null),
            'rsvp' => $this->ringkasRsvp(0, 0, 0, 0),
            'aktivitas' => [],
            'ucapan' => [],
        ];
    }

    /** @return list<array{label:string,nilai:int,delta:int,ikon:string}> */
    private function statistik(int $tamu, int $tamuBaru, int $terkirim, int $terkirimBaru, int $dilihat, int $dilihatBaru, int $hadir, int $hadirBaru): array
    {
        return [
            ['label' => 'Total Tamu', 'nilai' => $tamu, 'delta' => $tamuBaru, 'ikon' => 'beranda-tamu'],
            ['label' => 'Terkirim', 'nilai' => $terkirim, 'delta' => $terkirimBaru, 'ikon' => 'beranda-undangan'],
            ['label' => 'Dilihat', 'nilai' => $dilihat, 'delta' => $dilihatBaru, 'ikon' => 'beranda-lihat'],
            ['label' => 'Konfirmasi Hadir', 'nilai' => $hadir, 'delta' => $hadirBaru, 'ikon' => 'beranda-hadir'],
        ];
    }

    /**
     * @param  list<bool>  $selesai
     * @return array{tampil:bool,selesai:int,pct:int,item:list<array<string,mixed>>}
     */
    private function langkah(array $selesai, ?int $undanganId): array
    {
        $label = [
            ['Pilih tema', 'Tema dipilih di Edit Undangan'],
            ['Isi data undangan', 'Nama mempelai, tanggal, lokasi'],
            ['Tambah daftar tamu', 'Supaya tiap tamu punya tautan sendiri'],
            ['Terbitkan undangan', 'Tautan & QR siap dibagikan'],
        ];
        $berikut = collect($selesai)->search(fn ($ok) => ! $ok);
        $jumlah = collect($selesai)->filter()->count();

        $item = [];
        foreach ($label as $i => [$nama, $hint]) {
            $href = match (true) {
                $undanganId === null => $this->tautan('buat-undangan'),
                $i === 2 => $this->tautan('bagikan', ['undangan' => $undanganId]),
                default => $this->tautan('edit-undangan', ['undangan' => $undanganId]),
            };
            $item[] = ['no' => $i + 1, 'label' => $nama, 'hint' => $hint, 'ok' => $selesai[$i], 'sekarang' => $berikut === $i, 'href' => $href];
        }

        return ['tampil' => $jumlah < 4, 'selesai' => $jumlah, 'pct' => (int) round($jumlah / 4 * 100), 'item' => $item];
    }

    /** @return array<string,mixed> */
    private function ringkasRsvp(int $terkirim, int $hadir, int $tidak, int $belum): array
    {
        $total = $hadir + $tidak + $belum;
        $persen = fn (int $n) => $total > 0 ? (int) round($n / $total * 100) : 0;

        return [
            'terkirim' => $terkirim,
            'pct' => $terkirim > 0 ? min(100, (int) round($hadir / $terkirim * 100)) : 0,
            'hadir' => $hadir, 'tidak' => $tidak, 'belum' => $belum,
            'pHadir' => $persen($hadir), 'pTidak' => $persen($tidak), 'pBelum' => $persen($belum),
            'donat' => $total > 0
                ? 'conic-gradient(#16A34A 0 '.($hadir / $total * 360).'deg, #EF4444 0 '.(($hadir + $tidak) / $total * 360).'deg, #F59E0B 0 360deg)'
                : '#EEF0F3',
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $daftar
     * @return array{semua:int,utama:int,aktif:int,draf:int}
     */
    private function hitung(array $daftar): array
    {
        $koleksi = collect($daftar);

        return [
            'semua' => $koleksi->count(),
            'utama' => $koleksi->where('utama', true)->count(),
            'aktif' => $koleksi->where('status', 'aktif')->count(),
            'draf' => $koleksi->where('status', 'draf')->count(),
        ];
    }

    /**
     * Filter status + kolom cari. Pencarian nama tamu memakai satu kueri tambahan (rata, tidak tergantung jumlah data).
     *
     * @param  array<int,array<string,mixed>>  $daftar
     * @return array<int,array<string,mixed>>
     */
    private function saring(array $daftar): array
    {
        $hasil = collect($daftar)->filter(fn ($u) => match ($this->filter) {
            'utama' => $u['utama'],
            'aktif' => $u['status'] === 'aktif',
            'draf' => $u['status'] === 'draf',
            default => true,
        });

        $kata = Str::of($this->cari)->squish()->limit(80, '')->lower()->toString();
        if ($kata !== '') {
            $pola = '%'.addcslashes($kata, '%_\\').'%';
            $cocokTamu = Tamu::query()->whereIn('undangan_id', collect($daftar)->pluck('id'))
                ->whereRaw('lower(nama) like ?', [$pola])->distinct()->pluck('undangan_id')->all();
            $hasil = $hasil->filter(fn ($u) => in_array($u['id'], $cocokTamu, true)
                || str_contains(mb_strtolower($u['judul'].' '.$u['slug'].' '.$u['jenis']), $kata));
        }

        return $hasil->values()->all();
    }

    private function adaIsi(int $undanganId): bool
    {
        return ! empty(Undangan::query()->whereKey($undanganId)->value('isi'));
    }

    /**
     * Tujuh hari berakhir hari ini (tertua di kiri), dibandingkan dengan tujuh hari sebelumnya.
     *
     * @return array{total:int,tren:string,naik:bool,bar:list<array{v:int,hari:string,h:int,puncak:bool}>}
     */
    private function kunjungan(?int $undanganId): array
    {
        $mulai = now()->startOfDay()->subDays(6);
        $baris = $undanganId === null ? collect() : Tayangan::query()->where('undangan_id', $undanganId)->where('created_at', '>=', $mulai->copy()->subDays(7))
            ->selectRaw('date(created_at) as hari, count(*) as n')->groupBy('hari')->pluck('n', 'hari');

        $nilai = [];
        for ($i = 0; $i < 7; $i++) {
            $hari = $mulai->copy()->addDays($i);
            $nilai[] = ['v' => (int) ($baris[$hari->toDateString()] ?? 0), 'hari' => self::HARI[$hari->dayOfWeek]];
        }

        $total = array_sum(array_column($nilai, 'v'));
        $maks = max(1, ...array_column($nilai, 'v'));
        $sebelumnya = 0;
        for ($i = 1; $i <= 7; $i++) {
            $sebelumnya += (int) ($baris[$mulai->copy()->subDays($i)->toDateString()] ?? 0);
        }

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
        $rsvp = Rsvp::query()->where('undangan_id', $undanganId)->latest()->latest('id')->limit(4)->get()->map(fn ($r) => [
            'at' => $r->created_at,
            'nama' => $r->nama,
            'info' => $r->status === Rsvp::HADIR ? 'Mengonfirmasi hadir · '.$r->jumlah.' orang' : 'Berhalangan hadir',
            'jenis' => $r->status === Rsvp::HADIR ? 'hadir' : 'tidak',
        ]);

        $tayangan = Tayangan::query()->where('undangan_id', $undanganId)->whereNotNull('tamu_id')
            ->latest('created_at')->latest('id')->limit(4)->get();
        $namaTamu = Tamu::query()->where('undangan_id', $undanganId)->whereIn('id', $tayangan->pluck('tamu_id'))->pluck('nama', 'id');
        $dilihat = $tayangan->filter(fn ($t) => isset($namaTamu[$t->tamu_id]))->map(fn ($t) => [
            'at' => $t->created_at,
            'nama' => $namaTamu[$t->tamu_id],
            'info' => 'Membuka undangan',
            'jenis' => 'dilihat',
        ]);

        return $rsvp->concat($dilihat)->sortByDesc('at')->take(4)->map(fn ($a) => [
            'ini' => $this->inisial($a['nama']),
            'nama' => $a['nama'],
            'info' => $a['info'],
            'jenis' => $a['jenis'],
            'waktu' => $this->waktuRelatif($a['at']),
        ])->values()->all();
    }

    /**
     * Data dialog Bagikan. Id berasal dari properti terkunci dan baris daftar milik pengguna; QR dibuat hanya untuk yang terbit.
     *
     * @param  array<string,mixed>|null  $baris
     * @return array<string,mixed>|null
     */
    private function dataBagikan(?array $baris): ?array
    {
        if (! $baris) {
            return null;
        }

        $url = $baris['tautanTamu'] ?? ($baris['slug'] ? route('undangan.publik', $baris['slug']) : null);
        $terbit = $baris['status'] === 'aktif' && $url !== null;

        return $baris + [
            'url' => $url,
            'terbit' => $terbit,
            'qr' => $terbit ? KodeQr::svg($url, 96, '#10203A') : null,
            'unduhQr' => $terbit ? KodeQr::dataUri($url, 512) : null,
            'wa' => $url ? PesanUndangan::urlWa(null, 'Kami mengundang Anda ke '.$baris['judul'].'. Buka undangannya di sini: '.$url) : null,
        ];
    }

    /** Data ilustrasi untuk akun yang belum punya undangan; tidak disimpan dan tidak bisa diubah. */
    private function dataContoh(): array
    {
        $baris = fn (string $judul, string $jenis, string $status, string $tgl, int $tamu, int $dilihat, int $hadir, bool $utama) => [
            'id' => null, 'judul' => $judul, 'mono' => $this->monogram($judul), 'jenis' => $jenis, 'slug' => Str::slug($judul),
            'link' => $this->hostPublik().'/'.Str::slug($judul), 'tautanTamu' => null, 'status' => $status,
            'tanggal' => $tgl, 'tamu' => $tamu, 'dilihat' => $dilihat, 'hadir' => $hadir,
            'perubahan' => false, 'terbit' => $status === 'aktif' ? 'Sudah terbit' : 'Belum diterbitkan', 'utama' => $utama,
        ];
        $daftar = [$baris('Raka & Sinta', 'Pernikahan', 'aktif', '14 Feb 2027', 250, 168, 124, true), $baris('Ulang Tahun Nara', 'Ulang Tahun', 'draf', '21 Nov 2026', 60, 0, 0, false)];

        return [
            'kosong' => false,
            'contoh' => true,
            'daftar' => $this->saring($daftar),
            'hitung' => $this->hitung($daftar),
            'hero' => $daftar[0] + [
                'tempat' => 'Grand Ballroom, Jakarta', 'hadirPersen' => 50, 'jumlahHadir' => 124, 'jumlahTamu' => 250,
                'target' => now()->addDays(134)->setTime(10, 0)->toIso8601String(), 'kosong' => false,
            ],
            'stats' => $this->statistik(250, 5, 212, 18, 168, 24, 124, 9),
            'langkah' => ['tampil' => false, 'selesai' => 4, 'pct' => 100, 'item' => []],
            'kunjungan' => [
                'total' => 482, 'tren' => '+18% dari minggu lalu', 'naik' => true,
                'bar' => collect([48, 64, 57, 79, 112, 74, 48])->map(fn ($v, $i) => [
                    'v' => $v, 'hari' => self::HARI[$i], 'h' => (int) round($v / 112 * 140), 'puncak' => $v === 112,
                ])->all(),
            ],
            'rsvp' => $this->ringkasRsvp(212, 124, 22, 66),
            'aktivitas' => [
                ['ini' => 'BS', 'nama' => 'Budi Santoso', 'info' => 'Mengonfirmasi hadir · 2 orang', 'jenis' => 'hadir', 'waktu' => '5 mnt lalu'],
                ['ini' => 'KW', 'nama' => 'Keluarga Wijaya', 'info' => 'Membuka undangan', 'jenis' => 'dilihat', 'waktu' => '18 mnt lalu'],
                ['ini' => 'AL', 'nama' => 'Ayu Lestari', 'info' => 'Berhalangan hadir', 'jenis' => 'tidak', 'waktu' => '1 jam lalu'],
                ['ini' => 'DP', 'nama' => 'Dimas Pratama', 'info' => 'Terkirim via WhatsApp', 'jenis' => 'belum', 'waktu' => '2 jam lalu'],
            ],
            'ucapan' => [
                ['nama' => 'Nadia & Keluarga', 'waktu' => 'Hari ini, 09.12', 'pesan' => 'Selamat menempuh hidup baru, Raka & Sinta! Semoga selalu bahagia dan dimudahkan dalam segala hal.'],
                ['nama' => 'Om Hendra', 'waktu' => 'Kemarin', 'pesan' => 'Selamat ya! Kami sekeluarga pasti hadir. Semoga lancar sampai hari H.'],
            ],
        ];
    }

    private function hostPublik(): string
    {
        return parse_url(url('/'), PHP_URL_HOST) ?: 'undangyah.com';
    }

    /** "Raka & Sinta" -> "R&S" (papan); judul lain -> dua huruf pertama. */
    private function monogram(string $judul): string
    {
        $judul = trim($judul);
        if (preg_match('/^(\S+)\s*[&+]\s*(\S+)/u', $judul, $m)) {
            return mb_strtoupper(mb_substr($m[1], 0, 1).'&'.mb_substr($m[2], 0, 1));
        }

        return mb_strtoupper(mb_substr($judul, 0, 2));
    }

    private function inisial(string $nama): string
    {
        $kata = preg_split('/[\s&]+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(collect($kata)->map(fn ($k) => mb_substr($k, 0, 1))->take(2)->implode('')) ?: '?';
    }

    private function tanggal(?Carbon $t): string
    {
        return $t ? $t->format('j').' '.self::BULAN[$t->month - 1].' '.$t->year : '–';
    }

    private function waktuRelatif(Carbon $t): string
    {
        $detik = max(0, (int) $t->diffInSeconds(now(), false));

        return match (true) {
            $detik < 60 => 'baru saja',
            $detik < 3600 => intdiv($detik, 60).' mnt lalu',
            $detik < 86400 => intdiv($detik, 3600).' jam lalu',
            default => intdiv($detik, 86400).' hari lalu',
        };
    }
}
