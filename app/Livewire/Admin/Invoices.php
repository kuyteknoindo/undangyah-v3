<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AksiPesananAdmin;
use App\Models\LogAksiAdmin;
use App\Models\Pesanan;
use App\Services\CsvTamu;
use App\Services\KuerPesananAdmin;
use App\Services\TampilPesananAdmin;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Panel Admin: daftar invoice (nomor pesanan), antrean bukti transfer yang perlu ditinjau, dan ringkasan pendapatan. */
class Invoices extends Component
{
    use AksiPesananAdmin;

    public const PER_HALAMAN = 10;

    private const MAKS_EKSPOR = 5000;

    #[Url(as: 'cari', except: '')]
    public string $cari = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $urut = 'baru';

    public int $halaman = 1;

    /** Pesanan yang buktinya sedang ditinjau di dialog. */
    #[Locked]
    public ?int $tinjauId = null;

    public bool $dialogTinjau = false;

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->normalisasi();
    }

    private function normalisasi(): void
    {
        $this->cari = mb_substr(trim($this->cari), 0, 100);
        $this->status = in_array($this->status, KuerPesananAdmin::STATUS, true) ? $this->status : '';
        $this->urut = in_array($this->urut, KuerPesananAdmin::URUTAN, true) ? $this->urut : 'baru';
    }

    public function updated(string $nama): void
    {
        if (in_array($nama, ['cari', 'status', 'urut'], true)) {
            $this->normalisasi();
            $this->halaman = 1;
        }
    }

    public function pilihStatus(string $status): void
    {
        $this->status = $status;
        $this->normalisasi();
        $this->halaman = 1;
    }

    public function resetFilter(): void
    {
        $this->reset('cari', 'status');
        $this->halaman = 1;
    }

    public function ke(int $halaman): void
    {
        $this->halaman = max(1, $halaman);
    }

    public function tinjau(int $id): void
    {
        $p = $this->pesananAdmin($id);
        $this->tinjauId = $p->id;
        $this->dialogTinjau = true;
    }

    public function tutupTinjau(): void
    {
        $this->dialogTinjau = false;
        $this->tinjauId = null;
    }

    protected function setelahAksi(int $pesananId): void
    {
        // Setelah lunas/tolak/batal, tinjauan bukti itu selesai.
        if ($this->tinjauId === $pesananId) {
            $this->tutupTinjau();
        }
    }

    public function ekspor(): ?StreamedResponse
    {
        $admin = $this->pastikanAdmin();
        if (! $this->butuhSandi('Ekspor data invoice (CSV, berisi data pembeli)', 'ekspor')) {
            return null;
        }
        $kunci = 'invoice-ekspor:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak ekspor. Coba lagi sebentar lagi.', tipe: 'galat');

            return null;
        }
        RateLimiter::hit($kunci, 60);

        $baris = $this->kueri()->with('item')->limit(self::MAKS_EKSPOR)->get();
        if ($baris->isEmpty()) {
            $this->dispatch('toast', pesan: 'Tidak ada invoice untuk diekspor.', tipe: 'info');

            return null;
        }
        $isi = app(CsvTamu::class)->tulis(
            ['No. invoice', 'Diterbitkan', 'Jatuh tempo', 'Pembeli', 'Email', 'Item', 'Subtotal', 'Diskon', 'Biaya', 'Kode unik', 'Total', 'Metode', 'Status', 'Dibayar'],
            $baris->map(fn (Pesanan $p) => [
                $p->nomor, $p->created_at?->format('Y-m-d H:i'), $p->kedaluwarsa_at?->format('Y-m-d H:i'), $p->pembeli_nama, $p->pembeli_email, $p->ringkasItem(),
                $p->subtotal, $p->diskon, $p->biaya, $p->kode_unik, $p->total, $p->metode_label, $p->infoStatus()[0], $p->dibayar_at?->format('Y-m-d H:i'),
            ]),
        );

        LogAksiAdmin::catat($admin, 'ekspor_invoice', null, ['jumlah' => $baris->count(), 'status' => $this->status !== '' ? $this->status : null, 'cari' => trim($this->cari) !== '' ? trim($this->cari) : null]);

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'invoice-undangyah-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function kueri()
    {
        $kuer = app(KuerPesananAdmin::class);
        $q = Pesanan::query();
        $kuer->saring($q, $this->status, $this->cari);

        return $kuer->urutkan($q, $this->urut);
    }

    public function render()
    {
        $this->pastikanAdmin();
        $kuer = app(KuerPesananAdmin::class);
        $hitung = $kuer->hitungPerStatus($kuer->terapkanDasar(Pesanan::query(), $this->cari));
        $kpi = $kuer->kpi();

        $item = $this->kueri()->with('item')->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $this->halaman));
        if ($item->isEmpty() && $item->total() > 0) {
            $this->halaman = $item->lastPage();
            $item = $this->kueri()->with('item')->paginate(self::PER_HALAMAN, ['*'], 'halaman', $this->halaman);
        }

        $antrean = Pesanan::query()->where('status', Pesanan::MENUNGGU)->whereNotNull('bukti_path');
        $jumlahAntrean = (clone $antrean)->count();
        $perluTinjau = $antrean->orderByRaw('case when ditunda_at is null then 0 else 1 end')->orderBy('bukti_at')->orderBy('id')->with('item')->limit(10)->get();

        $segera = Pesanan::query()->where('status', Pesanan::MENUNGGU)->whereBetween('kedaluwarsa_at', [now(), now()->addDay()])->count();

        $tinjau = null;
        if ($this->tinjauId && ($p = Pesanan::with('item')->find($this->tinjauId))) {
            $tinjau = ['p' => $p, 'status' => $p->statusEfektif(), 'aksi' => $this->aksiBoleh($p), 'tujuan' => TampilPesananAdmin::tujuan($p), 'versiBukti' => $p->bukti_at?->timestamp ?? 0];
        }

        $bulanan = $kuer->pendapatanBulanan(6);
        $nilaiKpi = fn (string $k): int => (int) ($kpi[$k] ?? 0);

        return view('livewire.admin.invoices.index', [
            'item' => $item,
            'hitung' => $hitung,
            'kpi' => [
                'semua' => $nilaiKpi('semua'), 'tertagih' => $nilaiKpi('tertagih_nilai'),
                'lunas' => $nilaiKpi('lunas_n'), 'lunasNilai' => $nilaiKpi('lunas_nilai'), 'lunasBulan' => $nilaiKpi('lunas_bulan_nilai'),
                'menunggu' => $nilaiKpi('menunggu_n'), 'menungguNilai' => $nilaiKpi('menunggu_nilai'), 'segera' => $segera,
                'antrean' => $jumlahAntrean,
            ],
            'bulanan' => $bulanan,
            'maksBulanan' => max(1, ...array_column($bulanan, 'nilai')),
            'perluTinjau' => $perluTinjau,
            'tinjau' => $tinjau,
            'adaFilter' => $this->cari !== '' || $this->status !== '',
            'konfirmasi' => $this->dataKonfirmasi(),
        ])->layout('layouts::app', ['judul' => 'Invoices']);
    }
}
