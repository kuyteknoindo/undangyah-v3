<?php

namespace App\Livewire;

use App\Livewire\Concerns\PilihUndanganMilik;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Services\BukuTamuPengaturan;
use App\Services\CsvTamu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Buku tamu: ucapan yang masuk dari undangan publik, moderasi (sembunyikan, sematkan, hapus), buka/tutup kolom ucapan. */
class BukuTamu extends Component
{
    use PilihUndanganMilik;

    public const PER_HALAMAN = 10;

    private const MAKS_EKSPOR = 5000;

    private const FILTER = ['semua', 'tampil', 'sembunyi', 'sematan'];

    private const URUTAN = ['baru', 'lama', 'nama'];

    public bool $siap = false;

    public string $cari = '';

    public string $filter = 'semua';

    public string $urut = 'baru';

    public int $halaman = 1;

    /** Ucapan yang sedang menunggu konfirmasi hapus (sebaris, bukan dialog, seperti di desain). */
    #[Locked]
    public ?int $hapusId = null;

    /** Salinan ucapan yang baru dihapus untuk tombol Urungkan; diisi server, tidak bisa diubah klien. */
    #[Locked]
    public ?array $terhapus = null;

    public function mount(?string $undangan = null): void
    {
        $this->tentukanUndanganAwal($undangan);
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    protected function setelahPilihUndangan(): void
    {
        $this->cari = '';
        $this->filter = 'semua';
        $this->halaman = 1;
        $this->hapusId = null;
        $this->terhapus = null;
    }

    public function updatedCari(): void
    {
        $this->cari = mb_substr(trim($this->cari), 0, 100);
        $this->halaman = 1;
    }

    public function updatedUrut(): void
    {
        $this->urut = in_array($this->urut, self::URUTAN, true) ? $this->urut : 'baru';
        $this->halaman = 1;
    }

    public function pilihFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTER, true) ? $filter : 'semua';
        $this->halaman = 1;
    }

    public function resetFilter(): void
    {
        $this->cari = '';
        $this->filter = 'semua';
        $this->halaman = 1;
    }

    public function ke(int $halaman): void
    {
        $this->halaman = max(1, $halaman);
    }

    // ------------------------------------------------------------------ pengaturan kolom ucapan

    public function ubahBuka(): void
    {
        $u = $this->undanganWajib();
        $pengaturan = app(BukuTamuPengaturan::class);
        $sekarang = $pengaturan->ambil($u);
        $pengaturan->simpan($u, ! $sekarang['buka'], $sekarang['setujui']);
        $this->dispatch('toast', pesan: $sekarang['buka'] ? 'Kolom ucapan ditutup. Tamu tidak bisa mengirim ucapan baru.' : 'Kolom ucapan dibuka kembali.', tipe: 'sukses');
    }

    public function ubahSetujui(): void
    {
        $u = $this->undanganWajib();
        $pengaturan = app(BukuTamuPengaturan::class);
        $sekarang = $pengaturan->ambil($u);
        $pengaturan->simpan($u, $sekarang['buka'], ! $sekarang['setujui']);
        $this->dispatch('toast', pesan: $sekarang['setujui'] ? 'Ucapan baru langsung tampil.' : 'Ucapan baru tampil setelah kamu setujui.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ moderasi

    private function ucapanMilik(int $id): Ucapan
    {
        $u = $this->undanganWajib();

        return Ucapan::query()->where('undangan_id', $u->id)->findOrFail($id);
    }

    public function sematkan(int $id): void
    {
        $x = $this->ucapanMilik($id);
        if (! $x->disematkan) {
            $jumlah = Ucapan::query()->where('undangan_id', $x->undangan_id)->where('disematkan', true)->count();
            if ($jumlah >= Ucapan::MAKS_SEMATAN) {
                $this->dispatch('toast', pesan: 'Maksimal '.Ucapan::MAKS_SEMATAN.' ucapan yang disematkan. Lepas satu dulu.', tipe: 'galat');

                return;
            }
        }
        // Ucapan tersembunyi tidak ada artinya disematkan; menyematkan sekaligus menampilkannya.
        $x->forceFill(['disematkan' => ! $x->disematkan, 'disembunyikan' => $x->disematkan ? $x->disembunyikan : false])->save();
        $this->dispatch('toast', pesan: $x->disematkan ? 'Ucapan '.$x->nama.' disematkan.' : 'Sematan dilepas.', tipe: 'sukses');
    }

    public function sembunyikan(int $id): void
    {
        $x = $this->ucapanMilik($id);
        $sembunyi = ! $x->disembunyikan;
        $x->forceFill(['disembunyikan' => $sembunyi, 'disematkan' => $sembunyi ? false : $x->disematkan])->save();
        $this->dispatch('toast', pesan: $sembunyi ? 'Ucapan disembunyikan dari undangan.' : 'Ucapan ditampilkan kembali.', tipe: 'sukses');
    }

    public function tanyaHapus(int $id): void
    {
        $this->hapusId = $this->ucapanMilik($id)->id;
    }

    public function batalHapus(): void
    {
        $this->hapusId = null;
    }

    public function hapus(): void
    {
        if (! $this->hapusId) {
            return;
        }
        $x = $this->ucapanMilik($this->hapusId);
        $this->terhapus = $x->only(['id', 'undangan_id', 'tamu_id', 'nama', 'pesan', 'kode_tamu', 'disembunyikan', 'disematkan'])
            + ['created_at' => $x->created_at?->toDateTimeString(), 'updated_at' => $x->updated_at?->toDateTimeString()];
        $x->delete();
        $this->hapusId = null;
    }

    public function urungkan(): void
    {
        $d = $this->terhapus;
        $this->terhapus = null;
        $u = $this->undanganWajib();
        if (! $d || (int) $d['undangan_id'] !== $u->id) {
            return;
        }
        if (Ucapan::query()->whereKey($d['id'])->exists()) {
            return;
        }
        if ($d['tamu_id'] && ! $u->tamu()->whereKey($d['tamu_id'])->exists()) {
            $d['tamu_id'] = null;
        }
        Ucapan::query()->forceCreate($d);
        $this->dispatch('toast', pesan: 'Ucapan dipulihkan.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ ekspor

    public function ekspor(): ?StreamedResponse
    {
        $u = $this->undanganWajib();
        $kunci = 'buku-tamu-ekspor:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak ekspor. Coba lagi sebentar lagi.', tipe: 'galat');

            return null;
        }
        RateLimiter::hit($kunci, 60);

        $baris = $this->kueri($u)->limit(self::MAKS_EKSPOR)->get();
        if ($baris->isEmpty()) {
            $this->dispatch('toast', pesan: 'Tidak ada ucapan untuk diekspor.', tipe: 'info');

            return null;
        }
        $isi = app(CsvTamu::class)->tulis(['Nama', 'Ucapan', 'Waktu', 'Disematkan', 'Disembunyikan'], $baris->map(fn (Ucapan $x) => [
            $x->nama, $x->pesan, $x->created_at?->format('Y-m-d H:i'), $x->disematkan ? 'Ya' : 'Tidak', $x->disembunyikan ? 'Ya' : 'Tidak',
        ]));
        $nama = 'buku-tamu-'.($u->slug ?: 'undangan').'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ data

    private function kueri(Undangan $u): Builder
    {
        $q = Ucapan::query()->where('undangan_id', $u->id);
        $kata = addcslashes(mb_strtolower(trim($this->cari)), '\\%_');
        if ($kata !== '') {
            $q->where(fn ($w) => $w->whereRaw("lower(nama) like ? escape '\\'", ["%{$kata}%"])
                ->orWhereRaw("lower(pesan) like ? escape '\\'", ["%{$kata}%"]));
        }
        match ($this->filter) {
            'tampil' => $q->where('disembunyikan', false),
            'sembunyi' => $q->where('disembunyikan', true),
            'sematan' => $q->where('disematkan', true),
            default => null,
        };
        $q->orderByDesc('disematkan');
        match ($this->urut) {
            'lama' => $q->orderBy('id'),
            'nama' => $q->orderByRaw('lower(nama)')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        };

        return $q;
    }

    /** @return array{total:int,tampil:int,sematan:int,sembunyi:int} */
    private function hitung(Undangan $u): array
    {
        $r = Ucapan::query()->where('undangan_id', $u->id)->toBase()
            ->selectRaw('count(*) as total, sum(case when disembunyikan then 1 else 0 end) as sembunyi, sum(case when disematkan then 1 else 0 end) as sematan')
            ->first();
        $total = (int) ($r->total ?? 0);
        $sembunyi = (int) ($r->sembunyi ?? 0);

        return ['total' => $total, 'tampil' => $total - $sembunyi, 'sematan' => (int) ($r->sematan ?? 0), 'sembunyi' => $sembunyi];
    }

    public function render()
    {
        $daftar = $this->daftarUndangan();
        $u = $this->undanganTerpilih();
        $hitung = null;
        $item = null;
        $pengaturan = ['buka' => true, 'setujui' => false];

        if ($this->siap && $u) {
            $hitung = $this->hitung($u);
            $pengaturan = app(BukuTamuPengaturan::class)->ambil($u);
            $q = $this->kueri($u);
            $item = $q->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $this->halaman));
            if ($item->isEmpty() && $item->total() > 0) {
                $this->halaman = $item->lastPage();
                $item = $this->kueri($u)->paginate(self::PER_HALAMAN, ['*'], 'halaman', $this->halaman);
            }
        }

        return view('livewire.buku-tamu.index', [
            'daftar' => $daftar,
            'undangan' => $u,
            'hitung' => $hitung,
            'item' => $item,
            'pengaturan' => $pengaturan,
            'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : '#',
        ])->layout('layouts::app', ['judul' => 'Buku Tamu', 'sub' => 'Kelola undangan']);
    }
}
