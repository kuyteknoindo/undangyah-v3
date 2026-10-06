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

    public function __construct(private Notifikasi $notifikasi, private AturanPaket $aturan) {}

    /**
     * Tandai lunas dan aktifkan isinya (paket +durasi, add-on, voucher dihitung sekali, notifikasi).
     * Boleh dari `menunggu` maupun `kedaluwarsa` (uang sudah masuk setelah batas waktu). Pesanan batal/refund ditolak.
     *
     * Paket yang LEBIH RENDAH dari paket pembeli yang masih aktif (mis. paket tinggi aktif sesudah pesanan ini dibuat)
     * tidak pernah menurunkan paket: pesanan tetap lunas (uang sudah masuk), item paketnya tidak diterapkan, dan
     * pesanan ditandai `perlu_ditinjau_at` dengan catatan agar admin mengembalikan dana. Add-on pada pesanan yang
     * sama tetap diterapkan seperti biasa (pembeli sudah membayarnya dan add-on tidak bergantung pada tingkat paket).
     *
     * @return bool true bila status berubah sekarang
     */
    public function lunas(Pesanan $pesanan, ?string $catatan = null): bool
    {
        $pesan = null;
        $user = null;
        $tinjau = [];
        $berubah = DB::transaction(function () use ($pesanan, $catatan, &$pesan, &$user, &$tinjau) {
            $p = $this->kunci($pesanan);
            if (! in_array($p->status, [Pesanan::MENUNGGU, Pesanan::KEDALUWARSA], true)) {
                return false;
            }

            $user = User::lockForUpdate()->findOrFail($p->user_id);
            $ringkas = $this->aktifkan($p, $user, $peringatan, $tinjau);

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
            if ($tinjau !== []) {
                $p->perlu_ditinjau_at = now();
                $p->catatan_tinjau = implode(' ', $tinjau);
            }
            $p->save();

            $pesan = 'Pesanan '.$p->nomor.' ('.$p->ringkasItem().') sudah lunas. '.$ringkas;
            if ($tinjau !== []) {
                $pesan .= ' Tim kami akan menghubungimu untuk mengembalikan dana.';
            }

            return true;
        });

        if ($berubah) {
            $this->notifikasi->kirim($user, ModelNotifikasi::TIPE_PEMBAYARAN, $tinjau === [] ? 'Pembayaran diterima' : 'Pembayaran diterima, paket tidak diterapkan', $pesan, '/billing', ['pesanan' => $pesanan->nomor]);
            if ($tinjau !== []) {
                $this->beritahuAdminTinjau($pesanan, $tinjau);
            }
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

    /**
     * Tandai pesanan lunas sebagai refund (dicatat saja; pengembalian dana dilakukan di luar aplikasi). Dengan
     * $turunkanPaket, paket pengguna dikembalikan ke Gratis bila paket yang dibeli pesanan ini masih yang aktif.
     *
     * @return bool true bila status berubah sekarang
     */
    public function refund(Pesanan $pesanan, string $alasan, bool $turunkanPaket = false): bool
    {
        $alasan = trim($alasan);
        $turun = false;
        $berubah = DB::transaction(function () use ($pesanan, $alasan, $turunkanPaket, &$turun) {
            $p = $this->kunci($pesanan);
            if ($p->status !== Pesanan::LUNAS) {
                return false;
            }
            // Pesanan yang paketnya tidak pernah diterapkan (perlu ditinjau) tidak boleh menurunkan paket pembeli yang aktif dari pesanan lain.
            $paketDiterapkan = $p->perlu_ditinjau_at === null && $p->selesai_ditinjau_at === null;
            if ($turunkanPaket && $paketDiterapkan) {
                $itemPaket = $p->item->firstWhere('tipe', ItemPesanan::PAKET);
                $kode = $itemPaket ? (string) ($itemPaket->efek['paket'] ?? $itemPaket->kode) : null;
                $user = User::lockForUpdate()->find($p->user_id);
                if ($kode && $user && $user->paket === $kode) {
                    $user->paket = 'gratis';
                    $user->paket_sampai = null;
                    $user->save();
                    $turun = true;
                }
            }
            if ($p->perlu_ditinjau_at !== null) {
                $p->perlu_ditinjau_at = null;
                $p->selesai_ditinjau_at = now();
            }
            $p->status = Pesanan::REFUND;
            $p->refund_at = now();
            $p->catatan_admin = $alasan !== '' ? $alasan : $p->catatan_admin;
            $p->save();

            return true;
        });

        if ($berubah) {
            $this->notifikasi->kirim(
                $pesanan->user_id,
                ModelNotifikasi::TIPE_PEMBAYARAN,
                'Pesanan di-refund',
                'Pesanan '.$pesanan->nomor.' ditandai refund.'.($alasan !== '' ? ' Alasan: '.$alasan : '').($turun ? ' Paketmu kembali ke Gratis.' : ''),
                '/billing',
            );
        }
        $pesanan->refresh();

        return $berubah;
    }

    /**
     * Admin menyatakan pesanan "perlu ditinjau" sudah ditangani (mis. dana sudah dikembalikan di luar aplikasi).
     * Idempoten: pesanan yang tidak sedang perlu ditinjau tidak berubah.
     *
     * @return bool true bila ada yang berubah
     */
    public function selesaiTinjau(Pesanan $pesanan, ?string $catatan = null): bool
    {
        $catatan = $catatan !== null ? trim($catatan) : '';

        $berubah = DB::transaction(function () use ($pesanan, $catatan) {
            $p = $this->kunci($pesanan);
            if ($p->status !== Pesanan::LUNAS || $p->perlu_ditinjau_at === null) {
                return false;
            }
            $p->perlu_ditinjau_at = null;
            $p->selesai_ditinjau_at = now();
            if ($catatan !== '') {
                $p->catatan_tinjau = trim(($p->catatan_tinjau ? $p->catatan_tinjau."\n" : '').'Selesai ditinjau: '.$catatan);
            }
            $p->save();

            return true;
        });
        $pesanan->refresh();

        return $berubah;
    }

    /** Tandai satu pesanan menunggu yang lewat batas sebagai kedaluwarsa. $paksa: admin menandai sebelum batas waktu. */
    public function kedaluwarsakan(Pesanan $pesanan, bool $paksa = false): bool
    {
        $berubah = DB::transaction(function () use ($pesanan, $paksa) {
            $p = $this->kunci($pesanan);
            if ($p->status !== Pesanan::MENUNGGU) {
                return false;
            }
            if (! $paksa && (! $p->kedaluwarsa_at || $p->kedaluwarsa_at->isFuture())) {
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

    /** @param  list<string>  $alasan */
    private function beritahuAdminTinjau(Pesanan $pesanan, array $alasan): void
    {
        foreach (User::where('role', User::PERAN_ADMIN)->pluck('id') as $adminId) {
            $this->notifikasi->kirim(
                $adminId,
                ModelNotifikasi::TIPE_PEMBAYARAN,
                'Pesanan perlu ditinjau',
                'Pesanan '.$pesanan->nomor.' ('.$pesanan->pembeli_nama.') lunas tetapi paketnya tidak diterapkan. '.implode(' ', $alasan),
                '/admin/pesanan?status=tinjau&cari='.$pesanan->nomor,
                ['pesanan' => $pesanan->nomor],
            );
        }
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
     * @param  list<string>  $tinjau  diisi alasan bila ada item paket yang tidak diterapkan
     * @return string ringkasan untuk notifikasi
     */
    private function aktifkan(Pesanan $p, User $user, ?array &$peringatan, array &$tinjau): string
    {
        $peringatan = [];
        $tinjau = [];
        $ringkas = [];
        $item = $p->item->sortBy(fn (ItemPesanan $i) => $i->tipe === ItemPesanan::PAKET ? 0 : 1);

        foreach ($item as $it) {
            if ($it->tipe === ItemPesanan::PAKET) {
                $kode = (string) ($it->efek['paket'] ?? $it->kode);
                if ($bentrok = $this->aturan->bentrok($user, $kode)) {
                    $ket = AturanPaket::keterangan($bentrok['sampai']);
                    $tinjau[] = $bentrok['nama_aktif'].' sudah aktif '.$ket.'; pesanan '.$it->nama.' tidak diterapkan, kembalikan dana ke pembeli ('.TampilPesananAdmin::rp($it->harga).').';
                    $ringkas[] = $it->nama.' tidak diterapkan karena '.$bentrok['nama_aktif'].' kamu masih aktif '.$ket.'.';

                    continue;
                }
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
