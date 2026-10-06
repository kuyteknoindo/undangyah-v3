<?php

namespace App\Livewire;

use App\Exceptions\PesananGagal;
use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\MetodePembayaran;
use App\Models\Paket;
use App\Models\Voucher;
use App\Services\DataTagihan;
use App\Services\HitungPesanan;
use App\Services\Keranjang;
use App\Services\KonfirmasiPesanan;
use App\Services\PembuatPesanan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Checkout: keranjang di sesi server, voucher divalidasi server, harga SELALU dihitung ulang dari database oleh
 * HitungPesanan (properti di sini hanya kode). Membuat pesanan menunggu pembayaran (transfer manual).
 */
class Checkout extends Component
{
    public string $vInput = '';

    /** Kode voucher yang diterapkan; hanya diisi lewat pakaiVoucher() setelah lolos pemeriksaan server. */
    #[Locked]
    public string $voucher = '';

    public string $metode = '';

    public string $nama = '';

    public string $email = '';

    public string $hp = '';

    public string $alamat = '';

    public bool $setuju = false;

    /** Kode unik transfer untuk pratinjau; dicek ulang bebas-bentrok saat pesanan dibuat. */
    #[Locked]
    public int $unik = 0;

    public string $pesanVoucher = '';

    public string $galat = '';

    public bool $dicoba = false;

    public function mount(KonfirmasiPesanan $konfirmasi, DataTagihan $tagihan): void
    {
        $konfirmasi->sapuKedaluwarsa(auth()->id());
        $data = $tagihan->ambil(auth()->user());
        $this->nama = $data['nama'];
        $this->email = $data['email'];
        $this->hp = $data['hp'];
        $this->alamat = $data['alamat'];
        $this->unik = random_int(HitungPesanan::UNIK_MIN, HitungPesanan::UNIK_MAKS);
        $this->metode = MetodePembayaran::tersedia()->value('kode') ?? '';
    }

    public function tambahPaket(string $kode, Keranjang $keranjang): void
    {
        $paket = Paket::where('kode', $kode)->first();
        if (! $paket || ! $paket->dapatDibeli()) {
            return;
        }
        $keranjang->setPaket($paket->kode);
    }

    public function hapusPaket(Keranjang $keranjang): void
    {
        $keranjang->setPaket(null);
    }

    public function ubahAddon(string $kode, Keranjang $keranjang): void
    {
        if (AddOn::tersedia()->where('kode', $kode)->exists()) {
            $keranjang->ubahAddon($kode);
        } else {
            $keranjang->hapusAddon($kode);
        }
    }

    public function hapusAddon(string $kode, Keranjang $keranjang): void
    {
        $keranjang->hapusAddon($kode);
    }

    public function pakaiVoucher(HitungPesanan $hitung, Keranjang $keranjang): void
    {
        $this->pesanVoucher = '';
        $kode = Voucher::normalisasi($this->vInput);
        if ($kode === '') {
            $this->pesanVoucher = 'Masukkan kode voucher dulu.';

            return;
        }
        $kunci = 'voucher:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->pesanVoucher = 'Terlalu banyak percobaan voucher. Coba lagi sebentar lagi.';

            return;
        }
        RateLimiter::hit($kunci, 60);

        $h = $hitung->hitung(auth()->user(), $keranjang->ambil()['paket'], $keranjang->ambil()['addons']);
        $cek = $hitung->periksaVoucher($kode, $h['efektif'], $h['subtotal']);
        if (! $cek['ok']) {
            $this->pesanVoucher = $cek['pesan'];

            return;
        }
        $this->voucher = $cek['voucher']->kode;
        $this->vInput = '';
    }

    public function hapusVoucher(): void
    {
        $this->voucher = '';
        $this->pesanVoucher = '';
    }

    public function pilihMetode(string $kode): void
    {
        if (MetodePembayaran::tersedia()->where('kode', $kode)->exists()) {
            $this->metode = $kode;
        }
    }

    public function bayar(PembuatPesanan $pembuat, Keranjang $keranjang, DataTagihan $tagihan)
    {
        $this->dicoba = true;
        $this->galat = '';
        $user = auth()->user();

        $kunci = 'buat-pesanan:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->galat = 'Terlalu banyak percobaan membuat pesanan. Tunggu sebentar lalu coba lagi.';

            return;
        }
        RateLimiter::hit($kunci, 60);

        $data = $this->validate(
            DataTagihan::aturan() + ['setuju' => ['accepted']],
            DataTagihan::pesan() + ['setuju.accepted' => 'Centang persetujuan untuk melanjutkan.'],
        );

        $k = $keranjang->ambil();
        try {
            $pesanan = $pembuat->buat($user, $k['paket'], $k['addons'], $this->voucher ?: null, $this->metode, $data, $this->unik);
        } catch (PesananGagal $e) {
            $this->galat = $e->getMessage();

            return;
        }

        $tagihan->simpan($user, $data);
        $keranjang->kosongkan();
        session()->flash('toast', ['pesan' => $pesanan->wasRecentlyCreated ? 'Pesanan '.$pesanan->nomor.' dibuat.' : 'Kamu sudah punya pesanan yang sama: '.$pesanan->nomor.'.', 'tipe' => 'sukses']);

        return $this->redirectRoute('konfirmasi-pembayaran', ['pesanan' => $pesanan->nomor], navigate: true);
    }

    public function render(HitungPesanan $hitung, Keranjang $keranjang)
    {
        $user = auth()->user();
        $k = $keranjang->ambil();
        $h = $hitung->hitung($user, $k['paket'], $k['addons'], $this->voucher ?: null, $this->metode ?: null, $this->unik);

        $dimiliki = AddonPengguna::jumlahMilik($user->id);
        $addon = AddOn::tersedia()->get()->map(fn (AddOn $a) => [
            'model' => $a,
            'status' => $hitung->statusAddon($a, $h['efektif'], $dimiliki),
            'dipilih' => in_array($a->kode, array_column($h['item'], 'kode'), true),
        ]);
        $paketTersedia = Paket::tampil()->get()->filter->dapatDibeli();

        return view('livewire.checkout', [
            'h' => $h,
            'addon' => $addon,
            'metodeDaftar' => MetodePembayaran::tersedia()->get(),
            'paketBisaDitambah' => $paketTersedia->first(fn (Paket $p) => $h['paket'] === null && $p->kode !== 'gratis'),
            'tautanBilling' => Route::has('billing') ? route('billing') : '#',
            'tautanSyarat' => Route::has('syarat') ? route('syarat') : null,
            'paketSaatIni' => $user->paketAktif(),
            'sampai' => $user->paket_sampai,
        ])->layout('layouts::app', ['judul' => 'Checkout', 'sub' => 'Paket & Billing']);
    }
}
