<?php

namespace App\Livewire;

use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\Paket;
use App\Models\Pesanan;
use App\Services\AturanPaket;
use App\Services\DataTagihan;
use App\Services\HitungPesanan;
use App\Services\Keranjang;
use App\Services\KonfirmasiPesanan;
use App\Services\KuotaUndangan;
use App\Services\RingkasanAkun;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Paket & Billing: paket aktif, kuota, perbandingan paket, add-on (keranjang), riwayat pesanan, data tagihan. */
class PaketBilling extends Component
{
    private const BATAS_RINGKAS = 8;

    /** semua | menunggu | lunas | lain */
    public string $filter = 'semua';

    public bool $semua = false;

    public string $nama = '';

    public string $email = '';

    public string $hp = '';

    public string $alamat = '';

    /** Nomor invoice yang dibuka di dialog; hanya diisi lewat bukaInvoice (memeriksa kepemilikan). */
    #[Locked]
    public string $invoice = '';

    /** Catatan di kartu Data tagihan (hijau = berhasil, merah = galat), seperti banner papan. */
    public string $catatan = '';

    public bool $catatanGalat = false;

    public function mount(KonfirmasiPesanan $konfirmasi, DataTagihan $tagihan): void
    {
        $konfirmasi->sapuKedaluwarsa(auth()->id());
        $data = $tagihan->ambil(auth()->user());
        $this->nama = $data['nama'];
        $this->email = $data['email'];
        $this->hp = $data['hp'];
        $this->alamat = $data['alamat'];
    }

    public function ubahAddon(string $kode, Keranjang $keranjang): void
    {
        if (AddOn::tersedia()->where('kode', $kode)->exists()) {
            $keranjang->ubahAddon($kode);
        }
    }

    /** Pilih paket (beli/perpanjang) lalu lanjut ke Checkout. Kode divalidasi terhadap katalog. */
    public function pilihPaket(string $kode, Keranjang $keranjang, AturanPaket $aturan)
    {
        $paket = Paket::where('kode', $kode)->first();
        if (! $paket || ! $paket->dapatDibeli()) {
            $this->dispatch('toast', pesan: 'Paket ini belum bisa dibeli.', tipe: 'galat');

            return;
        }
        if ($bentrok = $aturan->bentrok(auth()->user(), $paket->kode)) {
            $this->dispatch('toast', pesan: $bentrok['pesan'], tipe: 'galat');

            return;
        }
        $keranjang->setPaket($paket->kode);

        return $this->redirectRoute('checkout', navigate: true);
    }

    public function kosongkanKeranjang(Keranjang $keranjang): void
    {
        $keranjang->kosongkan();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['semua', 'menunggu', 'lunas', 'lain'], true) ? $filter : 'semua';
        $this->semua = false;
    }

    public function ubahSemua(): void
    {
        $this->semua = ! $this->semua;
    }

    public function bukaInvoice(string $nomor): void
    {
        $this->invoice = Pesanan::milik(auth()->user())->where('nomor', $nomor)->value('nomor') ?? '';
    }

    public function tutupInvoice(): void
    {
        $this->invoice = '';
    }

    public function simpanTagihan(DataTagihan $tagihan): void
    {
        try {
            $data = $this->validate(DataTagihan::aturan(), DataTagihan::pesan());
        } catch (ValidationException $e) {
            $this->catatan = 'Periksa kolom yang ditandai merah.';
            $this->catatanGalat = true;

            throw $e;
        }
        $tagihan->simpan(auth()->user(), $data);
        $this->catatan = 'Data tagihan disimpan.';
        $this->catatanGalat = false;
    }

    public function render(RingkasanAkun $ringkasan, KuotaUndangan $kuota, HitungPesanan $hitung, Keranjang $keranjang, AturanPaket $aturan)
    {
        $user = auth()->user();
        $info = $ringkasan->paket($user);
        $aktif = $user->paketAktif();
        $paketAktif = Paket::where('kode', $aktif)->first();

        $k = $keranjang->ambil();
        $h = $hitung->hitung($user, $k['paket'], $k['addons']);
        $dimiliki = AddonPengguna::jumlahMilik($user->id);
        $addon = AddOn::tersedia()->get();
        $milikList = $addon->filter(fn (AddOn $a) => ($dimiliki[$a->kode] ?? 0) > 0);

        $pesanan = Pesanan::milik($user)->with('item')->latest('id')->limit(100)->get();
        $hitungStatus = ['semua' => $pesanan->count(), 'menunggu' => 0, 'lunas' => 0, 'lain' => 0];
        foreach ($pesanan as $p) {
            $s = $p->statusEfektif();
            $hitungStatus[$s === Pesanan::MENUNGGU ? 'menunggu' : ($s === Pesanan::LUNAS ? 'lunas' : 'lain')]++;
        }
        $disaring = $pesanan->filter(function (Pesanan $p) {
            $s = $p->statusEfektif();

            return match ($this->filter) {
                'menunggu' => $s === Pesanan::MENUNGGU,
                'lunas' => $s === Pesanan::LUNAS,
                'lain' => ! in_array($s, [Pesanan::MENUNGGU, Pesanan::LUNAS], true),
                default => true,
            };
        })->values();
        $menunggu = $pesanan->first(fn (Pesanan $p) => $p->menunggu());

        $daftarPaket = Paket::tampil()->get();
        // kode => pesan penolakan untuk paket yang lebih rendah dari paket aktif; tombol belinya dinonaktifkan di tampilan.
        $bentrok = $daftarPaket->mapWithKeys(fn (Paket $p) => [$p->kode => $aturan->bentrok($user, $p->kode)['pesan'] ?? null])->filter()->all();

        $durasiHari = max(1, ($paketAktif?->durasi_bulan ?: 12) * 30);
        $sisa = $info['sisaHari'];

        $faktur = $this->invoice !== '' ? Pesanan::milik($user)->where('nomor', $this->invoice)->with('item')->first() : null;

        return view('livewire.paket-billing', [
            'faktur' => $faktur,
            'info' => $info,
            'aktif' => $aktif,
            'paketAktif' => $paketAktif,
            'persenWaktu' => $sisa === null ? 0 : max(2, min(100, (int) round($sisa / $durasiHari * 100))),
            'batas' => $kuota->batas($user),
            'terpakai' => $kuota->terpakai($user),
            'tambahanKuota' => $kuota->tambahan($user),
            'daftarPaket' => $daftarPaket,
            'bentrok' => $bentrok,
            'addon' => $addon,
            'dimiliki' => $dimiliki,
            'milikList' => $milikList,
            'hitung' => $hitung,
            'h' => $h,
            'keranjang' => $k,
            'pesanan' => $this->semua ? $disaring : $disaring->take(self::BATAS_RINGKAS),
            'totalDisaring' => $disaring->count(),
            'batasRingkas' => self::BATAS_RINGKAS,
            'hitungStatus' => $hitungStatus,
            'menunggu' => $menunggu,
        ])->layout('layouts::app', ['judul' => 'Paket & Billing', 'sub' => 'Akun']);
    }
}
