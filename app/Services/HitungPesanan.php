<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\ItemPesanan;
use App\Models\MetodePembayaran;
use App\Models\Paket;
use App\Models\User;
use App\Models\Voucher;

/**
 * Satu-satunya tempat harga pesanan dihitung. Semua angka berasal dari database (paket, add-on, voucher, metode);
 * masukan dari klien hanya berupa KODE. Dipakai untuk pratinjau Checkout dan, ulang, saat pesanan dibuat.
 */
class HitungPesanan
{
    public const UNIK_MIN = 101;

    public const UNIK_MAKS = 999;

    /**
     * @param  list<string>  $addonKode
     * @return array{
     *   item:list<array{tipe:string,kode:string,nama:string,harga:int,efek:array}>,
     *   paket:?Paket, efektif:string, perpanjang:bool,
     *   subtotal:int, diskon:int, voucher:?Voucher, voucher_label:?string, voucher_galat:?string,
     *   metode:?MetodePembayaran, biaya:int, kode_unik:int, total:int, tanda:string
     * }
     */
    public function hitung(User $user, ?string $paketKode, array $addonKode, ?string $voucherKode = null, ?string $metodeKode = null, ?int $unik = null): array
    {
        $item = [];
        $paket = null;
        if ($paketKode) {
            $kandidat = Paket::where('kode', $paketKode)->first();
            if ($kandidat && $kandidat->dapatDibeli()) {
                $paket = $kandidat;
                $item[] = [
                    'tipe' => ItemPesanan::PAKET,
                    'kode' => $kandidat->kode,
                    'nama' => 'Paket '.$kandidat->nama,
                    'harga' => $kandidat->harga,
                    'efek' => ['paket' => $kandidat->kode, 'durasi_bulan' => $kandidat->durasi_bulan],
                ];
            }
        }

        $aktifSekarang = $user->paketAktif();
        $efektif = $paket?->kode ?? $aktifSekarang;
        $dimiliki = AddonPengguna::jumlahMilik($user->id);

        $daftarAddon = AddOn::tersedia()->whereIn('kode', array_values(array_unique($addonKode)))->get();
        foreach ($daftarAddon as $a) {
            if ($this->statusAddon($a, $efektif, $dimiliki) !== 'bisa') {
                continue;
            }
            $item[] = [
                'tipe' => ItemPesanan::ADDON,
                'kode' => $a->kode,
                'nama' => $a->nama,
                'harga' => $a->harga,
                'efek' => $a->efek ?? [],
            ];
        }

        $subtotal = array_sum(array_column($item, 'harga'));
        $voucher = null;
        $diskon = 0;
        $voucherLabel = null;
        $voucherGalat = null;
        if ($voucherKode !== null && $voucherKode !== '') {
            $cek = $this->periksaVoucher($voucherKode, $efektif, $subtotal);
            if ($cek['ok']) {
                $voucher = $cek['voucher'];
                $diskon = $cek['diskon'];
                $voucherLabel = $cek['label'];
            } else {
                $voucherGalat = $cek['pesan'];
            }
        }

        $setelah = max(0, $subtotal - $diskon);
        $metode = $metodeKode ? MetodePembayaran::tersedia()->where('kode', $metodeKode)->first() : null;
        $biaya = $metode ? $metode->biayaUntuk($setelah) : 0;
        $kodeUnik = 0;
        if ($metode && $metode->kode_unik && $setelah > 0) {
            $kodeUnik = $unik !== null && $unik >= self::UNIK_MIN && $unik <= self::UNIK_MAKS ? $unik : random_int(self::UNIK_MIN, self::UNIK_MAKS);
        }

        $kodeItem = array_map(fn ($i) => $i['tipe'].':'.$i['kode'], $item);
        sort($kodeItem);

        return [
            'item' => $item,
            'paket' => $paket,
            'efektif' => $efektif,
            'perpanjang' => $paket !== null && $aktifSekarang === $paket->kode,
            'subtotal' => $subtotal,
            'diskon' => $diskon,
            'voucher' => $voucher,
            'voucher_label' => $voucherLabel,
            'voucher_galat' => $voucherGalat,
            'metode' => $metode,
            'biaya' => $biaya,
            'kode_unik' => $kodeUnik,
            'total' => $setelah + $biaya + $kodeUnik,
            'tanda' => sha1(implode('|', $kodeItem).'#'.($metode?->kode ?? '').'#'.($voucher?->kode ?? '')),
        ];
    }

    /**
     * bisa | termasuk (sudah termasuk di paket) | dimiliki (add-on sekali beli yang sudah aktif) | terkunci (butuh paket berbayar).
     *
     * @param  array<string,int>  $dimiliki
     */
    public function statusAddon(AddOn $a, string $paketEfektif, array $dimiliki): string
    {
        $berbayar = $paketEfektif !== 'gratis';
        if ($berbayar && $a->termasuk_premium) {
            return 'termasuk';
        }
        if ($a->butuh_premium && ! $berbayar) {
            return 'terkunci';
        }
        if (! $a->multi && ($dimiliki[$a->kode] ?? 0) > 0) {
            return 'dimiliki';
        }

        return 'bisa';
    }

    /** @return array{ok:true,voucher:Voucher,diskon:int,label:string}|array{ok:false,pesan:string} */
    public function periksaVoucher(string $kode, string $paketEfektif, int $subtotal): array
    {
        $kode = Voucher::normalisasi($kode);
        $v = $kode !== '' ? Voucher::where('kode', $kode)->first() : null;
        if (! $v) {
            return ['ok' => false, 'pesan' => 'Kode voucher tidak ditemukan.'];
        }
        $galat = match ($v->status()) {
            'habis' => 'Voucher ini sudah habis dipakai.',
            'kedaluwarsa' => 'Voucher ini sudah kedaluwarsa.',
            'nonaktif' => 'Voucher ini sedang tidak aktif.',
            'belum' => 'Voucher ini belum berlaku.',
            default => null,
        };
        if ($galat) {
            return ['ok' => false, 'pesan' => $galat];
        }
        if ($v->min_belanja > 0 && $subtotal < $v->min_belanja) {
            return ['ok' => false, 'pesan' => 'Voucher ini berlaku untuk belanja minimal Rp '.number_format($v->min_belanja, 0, ',', '.').'.'];
        }
        if (! empty($v->paket_berlaku) && ! in_array($paketEfektif, $v->paket_berlaku, true)) {
            $nama = Paket::whereIn('kode', $v->paket_berlaku)->orderBy('urutan')->pluck('nama')->implode(' / ');

            return ['ok' => false, 'pesan' => 'Voucher '.$v->kode.' hanya berlaku untuk paket '.($nama ?: implode(' / ', $v->paket_berlaku)).'.'];
        }
        if ($subtotal <= 0) {
            return ['ok' => false, 'pesan' => 'Belum ada tagihan yang bisa diberi diskon.'];
        }

        if ($v->jenis === Voucher::PERSEN) {
            $diskon = (int) round($subtotal * min(100, $v->nilai) / 100);
            if ($v->maks_diskon !== null) {
                $diskon = min($diskon, $v->maks_diskon);
            }
            $label = 'Diskon '.min(100, $v->nilai).'%';
        } else {
            $diskon = $v->nilai;
            $label = 'Potongan Rp '.number_format($v->nilai, 0, ',', '.');
        }

        return ['ok' => true, 'voucher' => $v, 'diskon' => max(0, min($diskon, $subtotal)), 'label' => $label];
    }
}
