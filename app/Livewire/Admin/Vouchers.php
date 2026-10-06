<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Livewire\Concerns\MintaKonfirmasiSandi;
use App\Models\LogAksiAdmin;
use App\Models\Paket;
use App\Models\Pesanan;
use App\Models\Voucher;
use App\Services\CsvTamu;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin > Voucher: kode diskon untuk Checkout. Status habis/kedaluwarsa dihitung dari kuota dan tanggal. */
class Vouchers extends Component
{
    use HakAdminKatalog;
    use MintaKonfirmasiSandi;

    private const PER_HALAMAN = 15;

    private const MAKS_EKSPOR = 5000;

    public const STATUS = [
        'aktif' => ['hijau', 'Aktif'],
        'nonaktif' => ['abu', 'Nonaktif'],
        'habis' => ['kuning', 'Habis'],
        'kedaluwarsa' => ['merah', 'Kedaluwarsa'],
        'belum' => ['biru', 'Belum berlaku'],
    ];

    public string $q = '';

    /** semua | aktif | nonaktif | habis | kedaluwarsa | belum */
    public string $fs = 'semua';

    public int $hal = 1;

    public bool $dialogForm = false;

    #[Locked]
    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $f = [];

    public bool $dialogHapus = false;

    #[Locked]
    public ?int $hapusId = null;

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->f = $this->formKosong();
    }

    private function formKosong(): array
    {
        return ['kode' => '', 'jenis' => Voucher::PERSEN, 'nilai' => '', 'maks_diskon' => '', 'min_belanja' => '', 'batas_pakai' => '', 'paket' => [], 'dari' => '', 'sampai' => '', 'aktif' => true];
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 40);
        $this->hal = 1;
    }

    public function atur(string $status): void
    {
        $this->fs = $status === 'semua' || isset(self::STATUS[$status]) ? $status : 'semua';
        $this->hal = 1;
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'fs');
        $this->hal = 1;
    }

    public function sebelumnya(): void
    {
        $this->hal = max(1, $this->hal - 1);
    }

    public function berikutnya(): void
    {
        $this->hal++;
    }

    // ------------------------------------------------------------------ form

    public function bukaBaru(): void
    {
        $this->pastikanAdmin();
        $this->resetErrorBag();
        $this->editId = null;
        $this->f = $this->formKosong();
        $this->dialogForm = true;
    }

    public function bukaEdit(int $id): void
    {
        $this->pastikanAdmin();
        $v = Voucher::findOrFail($id);
        $this->resetErrorBag();
        $this->editId = $v->id;
        $this->f = [
            'kode' => $v->kode, 'jenis' => $v->jenis, 'nilai' => (string) $v->nilai, 'maks_diskon' => $v->maks_diskon ? (string) $v->maks_diskon : '',
            'min_belanja' => $v->min_belanja ? (string) $v->min_belanja : '', 'batas_pakai' => $v->batas_pakai === null ? '' : (string) $v->batas_pakai,
            'paket' => array_values(array_map('strval', (array) ($v->paket_berlaku ?? []))),
            'dari' => $v->berlaku_dari?->timezone(config('app.timezone'))->format('Y-m-d') ?? '',
            'sampai' => $v->berlaku_sampai?->timezone(config('app.timezone'))->format('Y-m-d') ?? '',
            'aktif' => (bool) $v->aktif,
        ];
        $this->dialogForm = true;
    }

    public function acak(): void
    {
        $this->pastikanAdmin();
        do {
            $kode = strtoupper(Str::random(8));
            $kode = preg_replace('/[^A-Z0-9]/', 'X', $kode);
        } while (Voucher::where('kode', $kode)->exists());
        $this->f['kode'] = $kode;
    }

    public function setJenis(string $jenis): void
    {
        $this->pastikanAdmin();
        $this->f['jenis'] = $jenis === Voucher::NOMINAL ? Voucher::NOMINAL : Voucher::PERSEN;
        $this->f['nilai'] = '';
        $this->f['maks_diskon'] = '';
    }

    public function simpan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $voucher = $this->editId ? Voucher::findOrFail($this->editId) : null;

        $this->f['kode'] = Voucher::normalisasi((string) ($this->f['kode'] ?? ''));
        foreach (['nilai', 'maks_diskon', 'min_belanja', 'batas_pakai'] as $k) {
            $this->f[$k] = $this->angka($this->f[$k] ?? '');
        }
        $persen = ($this->f['jenis'] ?? '') !== Voucher::NOMINAL;
        $this->f['jenis'] = $persen ? Voucher::PERSEN : Voucher::NOMINAL;
        $kodePaket = Paket::pluck('kode')->all();
        $this->f['paket'] = array_values(array_intersect((array) ($this->f['paket'] ?? []), $kodePaket));

        $this->validate([
            'f.kode' => ['required', 'regex:/^[A-Z0-9]{3,20}$/'],
            'f.nilai' => ['required', 'integer', 'min:1', $persen ? 'max:100' : 'max:100000000'],
            'f.maks_diskon' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'f.min_belanja' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'f.batas_pakai' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'f.dari' => ['nullable', 'date_format:Y-m-d'],
            'f.sampai' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'f.kode.regex' => 'Gunakan 3-20 huruf besar atau angka, tanpa spasi.',
            'f.nilai.min' => $persen ? 'Isi persen antara 1 dan 100.' : 'Potongan harus lebih dari Rp 0.',
            'f.nilai.max' => $persen ? 'Isi persen antara 1 dan 100.' : 'Potongan terlalu besar.',
        ], [
            'f.kode' => 'Kode voucher', 'f.nilai' => $persen ? 'Persen diskon' : 'Potongan', 'f.maks_diskon' => 'Maksimal diskon', 'f.min_belanja' => 'Minimal belanja',
            'f.batas_pakai' => 'Kuota pemakaian', 'f.dari' => 'Tanggal mulai', 'f.sampai' => 'Tanggal berakhir',
        ]);

        $galat = [];
        if (Voucher::query()->when($voucher, fn ($q) => $q->whereKeyNot($voucher->id))->where('kode', $this->f['kode'])->exists()) {
            $galat['f.kode'] = 'Kode '.$this->f['kode'].' sudah dipakai voucher lain.';
        }
        if ($this->f['dari'] !== '' && $this->f['sampai'] !== '' && $this->f['sampai'] < $this->f['dari']) {
            $galat['f.sampai'] = 'Tanggal berakhir tidak boleh sebelum tanggal mulai.';
        }
        if ($voucher && $this->f['batas_pakai'] !== '' && (int) $this->f['batas_pakai'] < $voucher->terpakai) {
            $galat['f.batas_pakai'] = 'Kuota tidak boleh di bawah jumlah yang sudah terpakai ('.$voucher->terpakai.').';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        $zona = config('app.timezone');
        $data = [
            'kode' => $this->f['kode'],
            'jenis' => $this->f['jenis'],
            'nilai' => (int) $this->f['nilai'],
            'maks_diskon' => $persen && $this->f['maks_diskon'] !== '' ? (int) $this->f['maks_diskon'] : null,
            'min_belanja' => (int) $this->f['min_belanja'],
            'batas_pakai' => $this->f['batas_pakai'] === '' ? null : (int) $this->f['batas_pakai'],
            'berlaku_dari' => $this->f['dari'] === '' ? null : Carbon::parse($this->f['dari'], $zona)->startOfDay(),
            'berlaku_sampai' => $this->f['sampai'] === '' ? null : Carbon::parse($this->f['sampai'], $zona)->endOfDay(),
            'paket_berlaku' => $this->f['paket'] === [] ? null : $this->f['paket'],
            'aktif' => (bool) $this->f['aktif'],
        ];
        // `terpakai` hanya berubah lewat pesanan (KonfirmasiPesanan), tidak pernah lewat form.
        $lama = $voucher ? ['jenis' => $voucher->jenis, 'nilai' => $voucher->nilai, 'aktif' => (bool) $voucher->aktif, 'batas_pakai' => $voucher->batas_pakai] : null;
        $voucher ? $voucher->update($data) : Voucher::create($data + ['terpakai' => 0]);
        LogAksiAdmin::catat(auth()->user(), $lama === null ? 'voucher_buat' : 'voucher_ubah', null, [
            'kode' => $data['kode'], 'sebelum' => $lama,
            'sesudah' => ['jenis' => $data['jenis'], 'nilai' => $data['nilai'], 'aktif' => $data['aktif'], 'batas_pakai' => $data['batas_pakai']],
        ]);

        $this->dialogForm = false;
        $this->dispatch('toast', pesan: 'Voucher '.$data['kode'].' disimpan.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ aksi baris

    public function ubahAktif(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $v = Voucher::findOrFail($id);
        $v->update(['aktif' => ! $v->aktif]);
        LogAksiAdmin::catat(auth()->user(), 'voucher_aktif', null, ['kode' => $v->kode, 'aktif' => (bool) $v->aktif]);
        $status = $v->status();
        $this->dispatch('toast', pesan: ! $v->aktif ? 'Voucher '.$v->kode.' dinonaktifkan.'
            : ($status === 'aktif' ? 'Voucher '.$v->kode.' diaktifkan.' : 'Voucher '.$v->kode.' dinyalakan, tetapi statusnya '.mb_strtolower(self::STATUS[$status][1]).'. Ubah kuota atau tanggal agar bisa dipakai.'), tipe: 'sukses');
    }

    public function tanyaHapus(int $id): void
    {
        $this->pastikanAdmin();
        $v = Voucher::findOrFail($id);
        if (Pesanan::where('voucher_id', $v->id)->exists()) {
            $this->dispatch('toast', pesan: 'Voucher ini sudah dipakai pesanan. Nonaktifkan saja agar riwayat tetap utuh.', tipe: 'galat');

            return;
        }
        $this->hapusId = $v->id;
        $this->dialogHapus = true;
    }

    public function hapus(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        if (! $this->butuhSandi('Hapus voucher', 'hapus')) {
            return;
        }
        $v = $this->hapusId ? Voucher::find($this->hapusId) : null;
        $this->dialogHapus = false;
        if (! $v) {
            return;
        }
        if (Pesanan::where('voucher_id', $v->id)->exists()) {
            $this->dispatch('toast', pesan: 'Voucher ini sudah dipakai pesanan. Nonaktifkan saja.', tipe: 'galat');

            return;
        }
        $v->delete();
        LogAksiAdmin::catat(auth()->user(), 'voucher_hapus', null, ['kode' => $v->kode]);
        $this->dispatch('toast', pesan: 'Voucher '.$v->kode.' dihapus.', tipe: 'sukses');
    }

    public function ekspor(): ?StreamedResponse
    {
        $this->pastikanAdmin();
        $kunci = 'voucher-ekspor:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->dispatch('toast', pesan: 'Terlalu sering mengekspor. Coba lagi sebentar lagi.', tipe: 'galat');

            return null;
        }
        RateLimiter::hit($kunci, 60);

        $baris = $this->tersaring($this->semua())->take(self::MAKS_EKSPOR);
        if ($baris->isEmpty()) {
            $this->dispatch('toast', pesan: 'Tidak ada voucher untuk diekspor.', tipe: 'info');

            return null;
        }
        $zona = config('app.timezone');
        $isi = app(CsvTamu::class)->tulis(
            ['Kode', 'Jenis', 'Nilai', 'Maks diskon', 'Minimal belanja', 'Berlaku untuk', 'Terpakai', 'Kuota', 'Berlaku dari', 'Berlaku sampai', 'Status'],
            $baris->map(fn (Voucher $v) => [
                $v->kode, $v->jenis, $v->nilai, $v->maks_diskon ?? '', $v->min_belanja, $v->paket_berlaku ? implode(' ', $v->paket_berlaku) : 'Semua paket',
                $v->terpakai, $v->batas_pakai ?? 'Tanpa batas', $v->berlaku_dari?->timezone($zona)->format('Y-m-d') ?? '', $v->berlaku_sampai?->timezone($zona)->format('Y-m-d') ?? '',
                self::STATUS[$v->status()][1],
            ]),
        );

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'voucher-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ tampilan

    /** @return Collection<int,Voucher> */
    private function semua()
    {
        return Voucher::orderByDesc('id')->get();
    }

    private function tersaring($semua)
    {
        $q = mb_strtolower(trim($this->q));

        return $semua->filter(fn (Voucher $v) => ($this->fs === 'semua' || $v->status() === $this->fs)
            && ($q === '' || str_contains(mb_strtolower($v->kode), $q)))->values();
    }

    public function render()
    {
        $this->pastikanAdmin();
        $semua = $this->semua();
        $hitung = array_fill_keys(['semua', ...array_keys(self::STATUS)], 0);
        $hitung['semua'] = $semua->count();
        foreach ($semua as $v) {
            $hitung[$v->status()]++;
        }
        $baris = $this->tersaring($semua);
        $halaman = max(1, (int) ceil($baris->count() / self::PER_HALAMAN));
        $hal = min(max(1, $this->hal), $halaman);
        $namaPaket = Paket::orderBy('urutan')->pluck('nama', 'kode');

        return view('livewire.admin.voucher.index', [
            'semua' => $semua,
            'baris' => $baris->slice(($hal - 1) * self::PER_HALAMAN, self::PER_HALAMAN),
            'total' => $baris->count(),
            'halAktif' => $hal,
            'halaman' => $halaman,
            'hitung' => $hitung,
            'terpakai' => (int) $semua->sum('terpakai'),
            'kuotaTotal' => (int) $semua->whereNotNull('batas_pakai')->sum('batas_pakai'),
            'namaPaket' => $namaPaket,
            'editVoucher' => $this->editId ? $semua->firstWhere('id', $this->editId) : null,
            'hapusVoucher' => $this->hapusId ? $semua->firstWhere('id', $this->hapusId) : null,
            'statusForm' => $this->dialogForm ? $this->statusPratinjau() : null,
        ])->layout('layouts::app', ['judul' => 'Voucher', 'sub' => 'Penjualan', 'admin' => true]);
    }

    /** Status yang akan dimiliki voucher bila form disimpan sekarang (hanya untuk keterangan di dialog). */
    private function statusPratinjau(): array
    {
        $zona = config('app.timezone');
        $tgl = fn ($s, $akhir) => is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? ($akhir ? Carbon::parse($s, $zona)->endOfDay() : Carbon::parse($s, $zona)->startOfDay()) : null;
        $v = new Voucher([
            'aktif' => ! empty($this->f['aktif']),
            'berlaku_dari' => $tgl($this->f['dari'] ?? null, false),
            'berlaku_sampai' => $tgl($this->f['sampai'] ?? null, true),
            'batas_pakai' => preg_replace('/\D/', '', (string) ($this->f['batas_pakai'] ?? '')) === '' ? null : (int) preg_replace('/\D/', '', (string) $this->f['batas_pakai']),
            'terpakai' => $this->editId ? (int) Voucher::whereKey($this->editId)->value('terpakai') : 0,
        ]);
        $s = $v->status();

        return [$s, self::STATUS[$s][0], self::STATUS[$s][1], [
            'aktif' => 'Bisa langsung dipakai di Checkout.', 'nonaktif' => 'Voucher dimatikan manual.', 'habis' => 'Pemakaian sudah mencapai kuota.',
            'kedaluwarsa' => 'Tanggal berlaku sudah lewat.', 'belum' => 'Tanggal mulai belum tiba.',
        ][$s]];
    }
}
