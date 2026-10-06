<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\ItemPesanan;
use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\Paket;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Perubahan status pesanan. Admin (Fase 7) memanggil lunas() dan tolak(); pengguna memakai batalkan(); perintah
 * terjadwal memakai kedaluwarsakan(). Semuanya idempoten: memanggil ulang pada pesanan yang sudah berubah tidak
 * mengubah apa pun dan mengembalikan false. Tiap perubahan berjalan dalam transaksi dengan baris pesanan terkunci.
 */
class KonfirmasiPesanan
{
    public const DISK_BUKTI = 'local';

    public function __construct(private Notifikasi $notifikasi) {}

    /**
     * Tandai lunas dan aktifkan isinya (paket +durasi, add-on, voucher dihitung sekali, notifikasi).
     * Boleh dari `menunggu` maupun `kedaluwarsa` (uang sudah masuk setelah batas waktu). Pesanan batal/refund ditolak.
     *
     * @return bool true bila status berubah sekarang
     */
    public function lunas(Pesanan $pesanan, ?string $catatan = null): bool
    {
        $pesan = null;
        $user = null;
        $berubah = DB::transaction(function () use ($pesanan, $catatan, &$pesan, &$user) {
            $p = $this->kunci($pesanan);
            if (! in_array($p->status, [Pesanan::MENUNGGU, Pesanan::KEDALUWARSA], true)) {
                return false;
            }

            $user = User::lockForUpdate()->findOrFail($p->user_id);
            $ringkas = $this->aktifkan($p, $user, $peringatan);

            if ($p->voucher_id && ! $p->voucher_ditahan) {
                Voucher::whereKey($p->voucher_id)->increment('terpakai');
                $p->voucher_ditahan = true;
            }

            $p->status = Pesanan::LUNAS;
            $p->dibayar_at = now();
            $p->bukti_ditolak_at = null;
            $catatanAkhir = collect([$catatan, ...$peringatan])->filter()->implode(' ');
            if ($catatanAkhir !== '') {
                $p->catatan_admin = trim(($p->catatan_admin ? $p->catatan_admin."\n" : '').$catatanAkhir);
            }
            $p->save();

            $pesan = 'Pesanan '.$p->nomor.' ('.$p->ringkasItem().') sudah lunas. '.$ringkas;

            return true;
        });

        if ($berubah) {
            $this->notifikasi->kirim($user, ModelNotifikasi::TIPE_PEMBAYARAN, 'Pembayaran diterima', $pesan, '/billing', ['pesanan' => $pesanan->nomor]);
        }
        $pesanan->refresh();

        return $berubah;
    }

    /**
     * Tolak bukti pembayaran. Bawaan: bukti dibuang dan pengguna diminta mengunggah ulang selama pesanan masih menunggu.
     * Dengan $batalkan, pesanan dibatalkan sekalian.
     *
     * @return bool true bila ada yang berubah
     */
    public function tolak(Pesanan $pesanan, string $alasan, bool $batalkan = false): bool
    {
        $alasan = trim($alasan);
        $berubah = DB::transaction(function () use ($pesanan, $alasan, $batalkan) {
            $p = $this->kunci($pesanan);
            if ($p->status !== Pesanan::MENUNGGU) {
                return false;
            }
            if ($batalkan) {
                $p->status = Pesanan::BATAL;
                $p->dibatalkan_at = now();
                $p->catatan_admin = $alasan !== '' ? $alasan : $p->catatan_admin;
                $this->lepasVoucher($p);
                $p->save();

                return true;
            }
            if ($p->bukti_path === null) {
                return false;
            }
            $this->hapusBerkas($p->bukti_path);
            $p->bukti_path = null;
            $p->bukti_nama = null;
            $p->bukti_at = null;
            $p->bukti_ditolak_at = now();
            $p->catatan_admin = $alasan !== '' ? $alasan : $p->catatan_admin;
            $p->save();

            return true;
        });

        if ($berubah) {
            $this->notifikasi->kirim(
                $pesanan->user_id,
                ModelNotifikasi::TIPE_PEMBAYARAN,
                $batalkan ? 'Pesanan dibatalkan' : 'Bukti pembayaran ditolak',
                $batalkan
                    ? 'Pesanan '.$pesanan->nomor.' dibatalkan.'.($alasan !== '' ? ' Alasan: '.$alasan : '')
                    : 'Bukti pembayaran untuk pesanan '.$pesanan->nomor.' ditolak.'.($alasan !== '' ? ' Alasan: '.$alasan : '').' Unggah bukti yang benar sebelum batas waktu.',
                '/konfirmasi-pembayaran/'.$pesanan->nomor,
            );
        }
        $pesanan->refresh();

        return $berubah;
    }

    /** Pengguna membatalkan pesanannya sendiri (hanya saat menunggu). */
    public function batalkan(Pesanan $pesanan): bool
    {
        $berubah = DB::transaction(function () use ($pesanan) {
            $p = $this->kunci($pesanan);
            if ($p->status !== Pesanan::MENUNGGU) {
                return false;
            }
            $p->status = Pesanan::BATAL;
            $p->dibatalkan_at = now();
            $this->lepasVoucher($p);
            $p->save();

            return true;
        });
        $pesanan->refresh();

        return $berubah;
    }

    /** Tandai satu pesanan menunggu yang lewat batas sebagai kedaluwarsa. */
    public function kedaluwarsakan(Pesanan $pesanan): bool
    {
        $berubah = DB::transaction(function () use ($pesanan) {
            $p = $this->kunci($pesanan);
            if ($p->status !== Pesanan::MENUNGGU || ! $p->kedaluwarsa_at || $p->kedaluwarsa_at->isFuture()) {
                return false;
            }
            $p->status = Pesanan::KEDALUWARSA;
            $this->lepasVoucher($p);
            $p->save();

            return true;
        });

        if ($berubah) {
            $this->notifikasi->kirim(
                $pesanan->user_id,
                ModelNotifikasi::TIPE_PEMBAYARAN,
                'Waktu pembayaran habis',
                'Pesanan '.$pesanan->nomor.' kedaluwarsa karena belum dibayar. Buat pesanan baru bila masih ingin membeli.',
                '/billing',
            );
        }
        $pesanan->refresh();

        return $berubah;
    }

    /** Sapu semua (atau milik satu pengguna) pesanan menunggu yang lewat batas. @return int jumlah yang ditandai */
    public function sapuKedaluwarsa(?int $userId = null): int
    {
        $jumlah = 0;
        Pesanan::query()
            ->where('status', Pesanan::MENUNGGU)
            ->where('kedaluwarsa_at', '<=', now())
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->each(function (Pesanan $p) use (&$jumlah) {
                if ($this->kedaluwarsakan($p)) {
                    $jumlah++;
                }
            });

        return $jumlah;
    }

    private function kunci(Pesanan $pesanan): Pesanan
    {
        return Pesanan::with('item')->lockForUpdate()->findOrFail($pesanan->id);
    }

    /** Voucher yang ditahan saat pesanan dibuat dikembalikan bila pesanan batal atau kedaluwarsa. */
    private function lepasVoucher(Pesanan $p): void
    {
        if ($p->voucher_id && $p->voucher_ditahan) {
            Voucher::whereKey($p->voucher_id)->where('terpakai', '>', 0)->decrement('terpakai');
            $p->voucher_ditahan = false;
        }
    }

    private function hapusBerkas(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK_BUKTI)->delete($path);
        }
    }

    /**
     * Terapkan isi pesanan ke akun. Paket dulu, lalu add-on (perpanjangan add-on butuh paket yang sudah aktif).
     *
     * @param  list<string>|null  $peringatan
     * @return string ringkasan untuk notifikasi
     */
    private function aktifkan(Pesanan $p, User $user, ?array &$peringatan): string
    {
        $peringatan = [];
        $ringkas = [];
        $item = $p->item->sortBy(fn (ItemPesanan $i) => $i->tipe === ItemPesanan::PAKET ? 0 : 1);

        foreach ($item as $it) {
            if ($it->tipe === ItemPesanan::PAKET) {
                $kode = (string) ($it->efek['paket'] ?? $it->kode);
                $bulan = (int) ($it->efek['durasi_bulan'] ?? Paket::where('kode', $kode)->value('durasi_bulan'));
                $sama = $user->paketAktif() === $kode;
                if ($sama && $user->paket_sampai === null && $kode !== 'gratis') {
                    // Sudah tanpa batas waktu (mis. diberikan admin): tidak ada yang diperpanjang.
                } else {
                    $dasar = $sama && $user->paket_sampai ? $user->paket_sampai->copy() : now();
                    $user->paket = $kode;
                    $user->paket_sampai = $bulan > 0 ? $dasar->addMonthsNoOverflow($bulan) : null;
                }
                $ringkas[] = 'Paket '.(Paket::where('kode', $kode)->value('nama') ?? ucfirst($kode)).($user->paket_sampai ? ' aktif sampai '.$user->paket_sampai->locale('id')->translatedFormat('j M Y').'.' : ' aktif.');

                continue;
            }

            $addon = AddOn::where('kode', $it->kode)->first();
            $bulan = (int) ($it->efek['perpanjang_bulan'] ?? 0);
            if ($bulan > 0) {
                if ($user->paketAktif() !== 'gratis' && $user->paket_sampai !== null) {
                    $user->paket_sampai = $user->paket_sampai->copy()->addMonthsNoOverflow($bulan);
                    $ringkas[] = 'Masa aktif diperpanjang sampai '.$user->paket_sampai->locale('id')->translatedFormat('j M Y').'.';
                } else {
                    $peringatan[] = 'Add-on "'.$it->nama.'" tidak memperpanjang apa pun karena tidak ada paket berbayar yang aktif; tinjau manual.';
                }
            }
            $this->catatKepemilikan($user->id, $it->kode, $addon?->multi ?? true);
            if ($bulan === 0) {
                $ringkas[] = 'Add-on "'.$it->nama.'" aktif.';
            }
        }
        $user->save();

        return implode(' ', $ringkas);
    }

    private function catatKepemilikan(int $userId, string $kode, bool $multi): void
    {
        $milik = AddonPengguna::where('user_id', $userId)->where('addon_kode', $kode)->first();
        if ($milik) {
            $milik->update(['jumlah' => $multi ? $milik->jumlah + 1 : 1]);
        } else {
            AddonPengguna::create(['user_id' => $userId, 'addon_kode' => $kode, 'jumlah' => 1]);
        }
    }
}
