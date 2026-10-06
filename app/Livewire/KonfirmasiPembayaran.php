<?php

namespace App\Livewire;

use App\Models\ItemPesanan;
use App\Models\Pesanan;
use App\Services\Keranjang;
use App\Services\KonfirmasiPesanan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Instruksi transfer, hitung mundur, dan unggah bukti untuk satu pesanan milik pengguna. Pesanan orang lain = 404.
 * Pelunasan TIDAK dilakukan di sini: admin yang memverifikasi bukti lalu memanggil KonfirmasiPesanan::lunas().
 */
class KonfirmasiPembayaran extends Component
{
    use WithFileUploads;

    private const EKSTENSI = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    #[Locked]
    public ?int $pesananId = null;

    /** Berkas yang sedang diunggah (sementara; dipindah ke disk privat setelah lolos validasi). */
    public $bukti;

    public bool $dialogBatal = false;

    public function mount(KonfirmasiPesanan $konfirmasi, ?string $pesanan = null): void
    {
        $konfirmasi->sapuKedaluwarsa(auth()->id());

        if ($pesanan !== null) {
            $this->pesananId = Pesanan::milik(auth()->id())->where('nomor', $pesanan)->firstOrFail()->id;

            return;
        }
        $this->pesananId = Pesanan::milik(auth()->id())
            ->where('status', Pesanan::MENUNGGU)->where('kedaluwarsa_at', '>', now())
            ->latest('id')->value('id');
    }

    public function updatedBukti(KonfirmasiPesanan $konfirmasi): void
    {
        $pesanan = $this->pesananMilik();
        if (! $pesanan) {
            return;
        }
        if (! $pesanan->menunggu()) {
            $this->reset('bukti');
            $this->dispatch('toast', pesan: 'Pesanan ini tidak lagi menunggu pembayaran.', tipe: 'galat');

            return;
        }

        $kunci = 'unggah-bukti:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->reset('bukti');
            $this->addError('bukti', 'Terlalu banyak unggahan. Coba lagi beberapa menit lagi.');

            return;
        }
        RateLimiter::hit($kunci, 600);

        $this->validate(
            ['bukti' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
            [
                'bukti.required' => 'Pilih gambar bukti pembayaran.',
                'bukti.file' => 'Bukti harus berupa berkas gambar.',
                'bukti.image' => 'Bukti harus berupa gambar JPG, PNG, atau WebP.',
                'bukti.mimes' => 'Bukti harus berupa gambar JPG, PNG, atau WebP.',
                'bukti.max' => 'Ukuran gambar maksimal 2 MB.',
                'bukti.uploaded' => 'Gambar gagal diunggah. Pastikan ukurannya maksimal 2 MB, lalu coba lagi.',
            ],
        );

        $mime = (string) $this->bukti->getMimeType();
        $ekstensi = self::EKSTENSI[$mime] ?? null;
        if ($ekstensi === null || @getimagesize($this->bukti->getRealPath()) === false) {
            $this->reset('bukti');
            $this->addError('bukti', 'Bukti harus berupa gambar JPG, PNG, atau WebP yang valid.');

            return;
        }

        $nama = Str::limit(trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', (string) $this->bukti->getClientOriginalName()) ?? ''), 100, '') ?: 'bukti.'.$ekstensi;
        $path = $this->bukti->storeAs('bukti-pembayaran', Str::random(40).'.'.$ekstensi, KonfirmasiPesanan::DISK_BUKTI);
        $this->bukti->delete();
        $this->reset('bukti');

        $lama = $pesanan->bukti_path;
        $pesanan->forceFill(['bukti_path' => $path, 'bukti_nama' => $nama, 'bukti_at' => now(), 'bukti_ditolak_at' => null])->save();
        if ($lama && $lama !== $path) {
            Storage::disk(KonfirmasiPesanan::DISK_BUKTI)->delete($lama);
        }
        $this->dispatch('toast', pesan: 'Bukti pembayaran terkirim. Admin akan memverifikasinya.', tipe: 'sukses');
    }

    public function hapusBukti(): void
    {
        $pesanan = $this->pesananMilik();
        if (! $pesanan || ! $pesanan->menunggu() || ! $pesanan->bukti_path) {
            return;
        }
        Storage::disk(KonfirmasiPesanan::DISK_BUKTI)->delete($pesanan->bukti_path);
        $pesanan->forceFill(['bukti_path' => null, 'bukti_nama' => null, 'bukti_at' => null])->save();
        $this->dispatch('toast', pesan: 'Bukti dihapus.', tipe: 'info');
    }

    public function batalkan(KonfirmasiPesanan $konfirmasi): void
    {
        $this->dialogBatal = false;
        $pesanan = $this->pesananMilik();
        if (! $pesanan) {
            return;
        }
        if ($konfirmasi->batalkan($pesanan)) {
            $this->dispatch('toast', pesan: 'Pesanan '.$pesanan->nomor.' dibatalkan.', tipe: 'info');
        } else {
            $this->dispatch('toast', pesan: 'Pesanan ini tidak bisa dibatalkan lagi.', tipe: 'galat');
        }
    }

    /** Masukkan lagi item pesanan (kedaluwarsa/batal) ke keranjang, lalu buat pesanan baru di Checkout. */
    public function pesanUlang(Keranjang $keranjang)
    {
        $pesanan = $this->pesananMilik();
        if (! $pesanan) {
            return;
        }
        $pesanan->load('item');
        $paket = $pesanan->item->firstWhere('tipe', ItemPesanan::PAKET)?->kode;
        $keranjang->isi($paket, $pesanan->item->where('tipe', ItemPesanan::ADDON)->pluck('kode')->all());

        return $this->redirectRoute('checkout', navigate: true);
    }

    private function pesananMilik(): ?Pesanan
    {
        return $this->pesananId ? Pesanan::milik(auth()->id())->find($this->pesananId) : null;
    }

    public function render(KonfirmasiPesanan $konfirmasi)
    {
        $pesanan = $this->pesananMilik();
        if ($pesanan && $pesanan->status === Pesanan::MENUNGGU && $pesanan->kedaluwarsa_at?->isPast()) {
            $konfirmasi->kedaluwarsakan($pesanan);
            $pesanan->refresh();
        }
        $pesanan?->load('item');

        $status = $pesanan?->statusEfektif();
        $data = $pesanan?->metode_data ?? [];
        $tujuan = null;
        if ($pesanan && $status === Pesanan::MENUNGGU) {
            $tujuan = ($data['tipe'] ?? 'manual') === 'instruksi'
                ? ['label' => $data['tujuan_label'] ?? 'Tujuan pembayaran', 'nilai' => $data['tujuan_nilai'] ?? '']
                : ['label' => 'Nomor rekening '.($data['bank'] ?? ''), 'nilai' => $data['nomor'] ?? ''];
            $tujuan['atas'] = $data['atas'] ?? null;
        }

        $efek = [];
        if ($pesanan && $status === Pesanan::LUNAS) {
            $user = auth()->user();
            foreach ($pesanan->item as $it) {
                $efek[] = $it->tipe === ItemPesanan::PAKET
                    ? $it->nama.' aktif'.($user->paket_sampai ? ' sampai '.$user->paket_sampai->locale('id')->translatedFormat('j M Y') : '').'.'
                    : 'Add-on aktif: '.$it->nama.'.';
            }
            $efek[] = 'Notifikasi pembayaran ditambahkan di Notifikasi, dan invoice tersedia di Paket & Billing.';
        }

        return view('livewire.konfirmasi-pembayaran', [
            'pesanan' => $pesanan,
            'status' => $status,
            'tujuan' => $tujuan,
            'langkah' => $data['langkah'] ?? [],
            'instruksi' => $data['instruksi'] ?? null,
            'placeholder' => (bool) ($data['placeholder'] ?? false),
            'efek' => $efek,
            'sisaDetik' => $pesanan && $status === Pesanan::MENUNGGU ? max(0, (int) now()->diffInSeconds($pesanan->kedaluwarsa_at, false)) : 0,
            'versiBukti' => $pesanan?->bukti_at?->timestamp,
        ])->layout('layouts::app', ['judul' => 'Konfirmasi Pembayaran', 'sub' => 'Paket & Billing']);
    }
}
