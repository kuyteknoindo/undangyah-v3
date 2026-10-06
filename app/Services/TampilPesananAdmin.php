<?php

namespace App\Services;

use App\Models\ItemPesanan;
use App\Models\Paket;
use App\Models\Pesanan;
use Carbon\CarbonInterface;

/** Pemformatan pesanan untuk halaman admin: rupiah, tanggal, tujuan bayar, riwayat status, dan pratinjau efek pelunasan. */
class TampilPesananAdmin
{
    public static function rp(int|float $n): string
    {
        return 'Rp '.number_format($n, 0, ',', '.');
    }

    public static function tgl(?CarbonInterface $t, bool $jam = true): string
    {
        if (! $t) {
            return '-';
        }

        return $t->copy()->locale('id')->translatedFormat($jam ? 'j M Y, H.i' : 'j M Y');
    }

    /** Sisa waktu bayar, mis. "5 jam 20 menit lagi" atau "lewat batas". */
    public static function sisa(?CarbonInterface $batas): string
    {
        if (! $batas) {
            return '-';
        }
        $menit = (int) floor(now()->diffInMinutes($batas, false));
        if ($menit <= 0) {
            return 'lewat batas';
        }
        if ($menit < 60) {
            return $menit.' menit lagi';
        }
        if ($menit < 1440) {
            return intdiv($menit, 60).' jam '.($menit % 60).' menit lagi';
        }

        return intdiv($menit, 1440).' hari '.intdiv($menit % 1440, 60).' jam lagi';
    }

    /** Tujuan bayar dari salinan data metode di pesanan. */
    public static function tujuan(Pesanan $p): string
    {
        $d = $p->metode_data ?? [];
        if (($d['tipe'] ?? 'manual') === 'instruksi') {
            return trim(($d['tujuan_label'] ?? 'Tujuan').': '.($d['tujuan_nilai'] ?? '-'));
        }
        $bagian = array_filter([trim(($d['bank'] ?? '').' '.($d['nomor'] ?? '')), isset($d['atas']) ? (preg_match('/^a\.?\s?n\b/i', $d['atas']) ? $d['atas'] : 'a.n. '.$d['atas']) : null]);

        return $bagian ? implode(' ', $bagian) : '-';
    }

    /**
     * Rincian biaya: item, lalu subtotal, voucher, biaya, kode unik. Total dipisah karena ditebalkan.
     *
     * @return array{item: list<array{label:string,jumlah:string}>, ringkas: list<array{label:string,jumlah:string}>}
     */
    public static function garis(Pesanan $p): array
    {
        $item = $p->item->map(fn (ItemPesanan $i) => ['label' => $i->namaTampil(), 'jumlah' => self::rp($i->harga)])->all();
        $ringkas = [['label' => 'Subtotal', 'jumlah' => self::rp($p->subtotal)]];
        if ($p->diskon > 0) {
            $ringkas[] = ['label' => 'Voucher'.($p->voucher_kode ? ' '.$p->voucher_kode : ''), 'jumlah' => '- '.self::rp($p->diskon)];
        }
        if ($p->biaya > 0) {
            $ringkas[] = ['label' => 'Biaya pembayaran', 'jumlah' => self::rp($p->biaya)];
        }
        if ($p->kode_unik > 0) {
            $ringkas[] = ['label' => 'Kode unik', 'jumlah' => self::rp($p->kode_unik)];
        }

        return ['item' => $item, 'ringkas' => $ringkas];
    }

    /**
     * Riwayat dari cap waktu yang ada, terlama dulu.
     *
     * @return list<array{judul:string,waktu:string,rinci:?string}>
     */
    public static function riwayat(Pesanan $p): array
    {
        $baris = [[$p->created_at, 'Pesanan dibuat', 'Menunggu pembayaran via '.($p->metode_label ?? '-').'.']];
        if ($p->bukti_at) {
            $baris[] = [$p->bukti_at, 'Bukti pembayaran diunggah', $p->bukti_nama];
        }
        if ($p->bukti_ditolak_at) {
            $baris[] = [$p->bukti_ditolak_at, 'Bukti pembayaran ditolak', $p->catatan_admin];
        }
        if ($p->ditunda_at && $p->status === Pesanan::MENUNGGU) {
            $baris[] = [$p->ditunda_at, 'Ditandai Pending oleh admin', null];
        }
        if ($p->dibayar_at) {
            $baris[] = [$p->dibayar_at, 'Pembayaran dikonfirmasi lunas', null];
        }
        if ($p->refund_at) {
            $baris[] = [$p->refund_at, 'Ditandai refund', $p->catatan_admin];
        }
        if ($p->dibatalkan_at) {
            $baris[] = [$p->dibatalkan_at, 'Pesanan dibatalkan', $p->catatan_admin];
        }
        if ($p->status === Pesanan::KEDALUWARSA || ($p->status === Pesanan::MENUNGGU && $p->kedaluwarsa_at?->isPast())) {
            $baris[] = [$p->kedaluwarsa_at, 'Waktu pembayaran habis', null];
        }
        usort($baris, fn ($a, $b) => ($a[0]?->getTimestamp() ?? 0) <=> ($b[0]?->getTimestamp() ?? 0));

        return array_map(fn ($b) => ['judul' => $b[1], 'waktu' => self::tgl($b[0]), 'rinci' => $b[2] ?: null], $baris);
    }

    /**
     * Efek yang akan terjadi bila pesanan ditandai lunas, untuk dialog konfirmasi.
     *
     * @return list<string>
     */
    public static function efekLunas(Pesanan $p): array
    {
        $efek = [];
        foreach ($p->item as $i) {
            if ($i->tipe === ItemPesanan::PAKET) {
                $kode = (string) ($i->efek['paket'] ?? $i->kode);
                $bulan = (int) ($i->efek['durasi_bulan'] ?? Paket::where('kode', $kode)->value('durasi_bulan'));
                $efek[] = $i->nama.' diaktifkan'.($bulan > 0 ? ' selama '.$bulan.' bulan' : '').'.';
            } else {
                $bulan = (int) ($i->efek['perpanjang_bulan'] ?? 0);
                $efek[] = $bulan > 0 ? 'Masa aktif diperpanjang '.$bulan.' bulan ('.$i->nama.').' : 'Add-on "'.$i->nama.'" diaktifkan.';
            }
        }
        if ($p->voucher_id && ! $p->voucher_ditahan) {
            $efek[] = 'Pemakaian voucher '.$p->voucher_kode.' dihitung.';
        }
        $efek[] = 'Pembeli menerima notifikasi pembayaran diterima.';

        return $efek;
    }
}
