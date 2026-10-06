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

/** Panel Admin: daftar semua pesanan, detail, bukti transfer, dan aksi status (lewat KonfirmasiPesanan). */
class DataPesanan extends Component
{
    use AksiPesananAdmin;

    public const PER_HALAMAN = 10;

    private const MAKS_EKSPOR = 5000;

    #[Url(as: 'cari', except: '')]
    public string $cari = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $metode = '';

    public string $periode = '';

    public string $dari = '';

    public string $sampai = '';

    public string $urut = 'baru';

    public int $halaman = 1;

    #[Locked]
    public ?int $detailId = null;

    public bool $dialogDetail = false;

    public string $catatan = '';

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->normalisasi();
    }

    private function normalisasi(): void
    {
        $this->cari = mb_substr(trim($this->cari), 0, 100);
        $this->status = in_array($this->status, [...KuerPesananAdmin::STATUS, KuerPesananAdmin::TINJAU], true) ? $this->status : '';
        $this->periode = in_array($this->periode, KuerPesananAdmin::PERIODE, true) ? $this->periode : '';
        $this->urut = in_array($this->urut, KuerPesananAdmin::URUTAN, true) ? $this->urut : 'baru';
        $this->metode = mb_substr($this->metode, 0, 80);
        $this->dari = KuerPesananAdmin::tanggal($this->dari) ? $this->dari : '';
        $this->sampai = KuerPesananAdmin::tanggal($this->sampai) ? $this->sampai : '';
    }

    public function updated(string $nama): void
    {
        if (in_array($nama, ['cari', 'status', 'metode', 'periode', 'dari', 'sampai', 'urut'], true)) {
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
        $this->reset('cari', 'status', 'metode', 'periode', 'dari', 'sampai');
        $this->halaman = 1;
    }

    public function ke(int $halaman): void
    {
        $this->halaman = max(1, $halaman);
    }

    // ------------------------------------------------------------------ detail

    public function buka(int $id): void
    {
        $p = $this->pesananAdmin($id);
        $this->detailId = $p->id;
        $this->catatan = (string) $p->catatan_internal;
        $this->dialogDetail = true;
    }

    public function tutupDetail(): void
    {
        $this->dialogDetail = false;
        $this->detailId = null;
    }

    public function simpanCatatan(): void
    {
        $this->pastikanAdmin();
        if (! $this->detailId) {
            return;
        }
        $this->validate(['catatan' => ['nullable', 'string', 'max:1000']], ['catatan.max' => 'Catatan maksimal 1.000 karakter.']);
        $p = Pesanan::findOrFail($this->detailId);
        $teks = trim($this->catatan);
        $p->forceFill(['catatan_internal' => $teks !== '' ? $teks : null])->save();
        $this->dispatch('toast', pesan: 'Catatan admin disimpan.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ ekspor

    public function ekspor(): ?StreamedResponse
    {
        $admin = $this->pastikanAdmin();
        if (! $this->butuhSandi('Ekspor data pesanan (CSV, berisi data pembeli)', 'ekspor')) {
            return null;
        }
        $kunci = 'pesanan-ekspor:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak ekspor. Coba lagi sebentar lagi.', tipe: 'galat');

            return null;
        }
        RateLimiter::hit($kunci, 60);

        $baris = $this->kueri()->with('item')->limit(self::MAKS_EKSPOR)->get();
        if ($baris->isEmpty()) {
            $this->dispatch('toast', pesan: 'Tidak ada pesanan untuk diekspor.', tipe: 'info');

            return null;
        }
        $isi = app(CsvTamu::class)->tulis(
            ['No. pesanan', 'Tanggal', 'Pembeli', 'Email', 'HP', 'Item', 'Subtotal', 'Diskon', 'Voucher', 'Biaya', 'Kode unik', 'Total', 'Metode', 'Status', 'Dibayar'],
            $baris->map(fn (Pesanan $p) => [
                $p->nomor, $p->created_at?->format('Y-m-d H:i'), $p->pembeli_nama, $p->pembeli_email, $p->pembeli_hp, $p->ringkasItem(),
                $p->subtotal, $p->diskon, $p->voucher_kode, $p->biaya, $p->kode_unik, $p->total, $p->metode_label, $p->infoStatus()[0], $p->dibayar_at?->format('Y-m-d H:i'),
            ]),
        );

        LogAksiAdmin::catat($admin, 'ekspor_pesanan', null, ['jumlah' => $baris->count(), 'status' => $this->status !== '' ? $this->status : null, 'cari' => trim($this->cari) !== '' ? trim($this->cari) : null]);

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'pesanan-undangyah-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ data

    private function kueri(bool $denganStatus = true)
    {
        $kuer = app(KuerPesananAdmin::class);
        $q = Pesanan::query();
        $kuer->saring($q, $denganStatus ? $this->status : '', $this->cari, $this->metode, $this->periode, $this->dari, $this->sampai);

        return $kuer->urutkan($q, $this->urut);
    }

    protected function setelahAksi(int $pesananId): void
    {
        // Detail tetap terbuka agar admin melihat hasilnya.
    }

    public function render()
    {
        $this->pastikanAdmin();
        $kuer = app(KuerPesananAdmin::class);
        $hitung = $kuer->hitungPerStatus($kuer->terapkanDasar(Pesanan::query(), $this->cari, $this->metode, $this->periode, $this->dari, $this->sampai));
        $kpi = $kuer->kpi();

        $item = $this->kueri()->with('item')->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $this->halaman));
        if ($item->isEmpty() && $item->total() > 0) {
            $this->halaman = $item->lastPage();
            $item = $this->kueri()->with('item')->paginate(self::PER_HALAMAN, ['*'], 'halaman', $this->halaman);
        }

        $detail = null;
        if ($this->detailId && ($p = Pesanan::with(['item', 'pengguna:id,name,email', 'voucher:id,kode'])->find($this->detailId))) {
            $detail = [
                'p' => $p,
                'status' => $p->statusEfektif(),
                'garis' => TampilPesananAdmin::garis($p),
                'riwayat' => TampilPesananAdmin::riwayat($p),
                'tujuan' => TampilPesananAdmin::tujuan($p),
                'aksi' => $this->aksiBoleh($p),
                'versiBukti' => $p->bukti_at?->timestamp ?? 0,
            ];
        }

        $nilaiKpi = fn (string $k): int => (int) ($kpi[$k] ?? 0);

        return view('livewire.admin.pesanan.index', [
            'item' => $item,
            'hitung' => $hitung,
            'kpi' => [
                'hari' => $nilaiKpi('hari_n'), 'hariNilai' => $nilaiKpi('hari_lunas_nilai'),
                'bulan' => $nilaiKpi('bulan_n'), 'bulanLunas' => $nilaiKpi('bulan_lunas_n'),
                'lunasNilai' => $nilaiKpi('lunas_nilai'), 'lunasBulan' => $nilaiKpi('lunas_bulan_nilai'),
                'menunggu' => $nilaiKpi('menunggu_n'), 'menungguNilai' => $nilaiKpi('menunggu_nilai'),
                'semua' => $nilaiKpi('semua'), 'pernahBayar' => $nilaiKpi('pernah_bayar'),
            ],
            'daftarMetode' => Pesanan::query()->whereNotNull('metode_label')->distinct()->orderBy('metode_label')->pluck('metode_label'),
            'rentangSalah' => $this->periode === 'custom' && $this->dari !== '' && $this->sampai !== '' && $this->dari > $this->sampai,
            'adaFilter' => $this->cari !== '' || $this->status !== '' || $this->metode !== '' || $this->periode !== '',
            'detail' => $detail,
            'konfirmasi' => $this->dataKonfirmasi(),
        ])->layout('layouts::app', ['judul' => 'Data Pesanan']);
    }
}
