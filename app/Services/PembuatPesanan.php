<?php

namespace App\Services;

use App\Exceptions\PesananGagal;
use App\Models\ItemPesanan;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Membuat pesanan dari KODE (bukan harga): semuanya dihitung ulang di server dalam satu transaksi. */
class PembuatPesanan
{
    public const MAKS_MENUNGGU = 3;

    public function __construct(private HitungPesanan $hitung, private KonfirmasiPesanan $konfirmasi) {}

    /**
     * @param  list<string>  $addonKode
     * @param  array{nama:string,email:string,hp?:?string,alamat?:?string}  $pembeli
     *
     * @throws PesananGagal
     */
    public function buat(User $user, ?string $paketKode, array $addonKode, ?string $voucherKode, string $metodeKode, array $pembeli, ?int $unik = null): Pesanan
    {
        $pesanan = null;
        for ($coba = 0; $coba < 5; $coba++) {
            try {
                $pesanan = DB::transaction(fn () => $this->simpan($user, $paketKode, $addonKode, $voucherKode, $metodeKode, $pembeli, $unik));
                break;
            } catch (QueryException $e) {
                // Nomor invoice bentrok dengan permintaan serentak: ulangi dengan nomor berikutnya.
                if ($coba === 4 || ! str_contains(strtolower($e->getMessage()), 'nomor')) {
                    throw $e;
                }
            }
        }

        if ($pesanan->wasRecentlyCreated && $pesanan->total === 0) {
            $this->konfirmasi->lunas($pesanan, 'Total Rp 0 setelah voucher: aktif otomatis.');
            $pesanan->refresh();
        }

        return $pesanan;
    }

    private function simpan(User $user, ?string $paketKode, array $addonKode, ?string $voucherKode, string $metodeKode, array $pembeli, ?int $unik): Pesanan
    {
        // Kunci baris voucher lebih dulu agar batas pakai tidak terlampaui oleh dua pesanan serentak.
        if ($voucherKode !== null && $voucherKode !== '') {
            Voucher::where('kode', Voucher::normalisasi($voucherKode))->lockForUpdate()->first();
        }
        // Paket aktif dibaca dari DB (bukan dari model di memori) karena aturan paket bergantung padanya.
        $user = User::findOrFail($user->id);
        $h = $this->hitung->hitung($user, $paketKode, $addonKode, $voucherKode, $metodeKode, $unik);

        if ($h['paket_galat'] !== null) {
            throw new PesananGagal($h['paket_galat']);
        }
        if ($h['item'] === []) {
            throw new PesananGagal('Keranjang kosong atau item tidak bisa dibeli.');
        }
        if ($h['metode'] === null) {
            throw new PesananGagal('Pilih metode pembayaran yang tersedia.');
        }
        if ($voucherKode !== null && $voucherKode !== '' && $h['voucher'] === null) {
            throw new PesananGagal($h['voucher_galat'] ?? 'Voucher tidak bisa dipakai.');
        }

        $menunggu = Pesanan::milik($user)->where('status', Pesanan::MENUNGGU)->where('kedaluwarsa_at', '>', now());
        $sama = (clone $menunggu)->where('tanda_item', $h['tanda'])->latest('id')->first();
        if ($sama) {
            return $sama;
        }
        // Satu voucher hanya sekali per akun (pesanan menunggu atau lunas); tanpa ini voucher Rp 0 bisa diulang tanpa henti.
        if ($h['voucher'] !== null && Pesanan::where('user_id', $user->id)->where('voucher_id', $h['voucher']->id)
            ->whereIn('status', [Pesanan::MENUNGGU, Pesanan::LUNAS])->exists()) {
            throw new PesananGagal('Voucher ini sudah pernah kamu pakai.');
        }
        if ($menunggu->count() >= self::MAKS_MENUNGGU) {
            throw new PesananGagal('Kamu punya '.self::MAKS_MENUNGGU.' pesanan yang menunggu pembayaran. Selesaikan atau batalkan salah satunya dulu.');
        }

        $metode = $h['metode'];
        $kodeUnik = $h['kode_unik'];
        $total = $h['total'];
        if ($kodeUnik > 0) {
            // Total transfer harus unik di antara pesanan menunggu pada metode yang sama agar admin bisa mencocokkan mutasi.
            [$kodeUnik, $total] = $this->unikBebas($h, $kodeUnik);
        }

        $jam = max(1, min(168, (int) Pengaturan::ambil('pembayaran.kedaluwarsa_jam', 24)));
        $pesanan = Pesanan::create([
            'nomor' => $this->nomorBerikut(),
            'user_id' => $user->id,
            'subtotal' => $h['subtotal'],
            'diskon' => $h['diskon'],
            'biaya' => $h['biaya'],
            'kode_unik' => $kodeUnik,
            'total' => $total,
            'voucher_id' => $h['voucher']?->id,
            'voucher_kode' => $h['voucher']?->kode,
            'voucher_ditahan' => $h['voucher'] !== null,
            'metode_id' => $metode->id,
            'metode_kode' => $metode->kode,
            'metode_label' => $metode->label,
            'metode_data' => array_merge(['tipe' => $metode->tipe, 'keterangan' => $metode->keterangan], $metode->data ?? []),
            'status' => Pesanan::MENUNGGU,
            'tanda_item' => $h['tanda'],
            'kedaluwarsa_at' => now()->addHours($jam),
            'pembeli_nama' => trim($pembeli['nama']),
            'pembeli_email' => trim($pembeli['email']),
            'pembeli_hp' => ($pembeli['hp'] ?? '') !== '' ? trim($pembeli['hp']) : null,
            'pembeli_alamat' => ($pembeli['alamat'] ?? '') !== '' ? trim($pembeli['alamat']) : null,
        ]);

        foreach ($h['item'] as $i => $it) {
            ItemPesanan::create(['pesanan_id' => $pesanan->id, 'urutan' => $i] + $it);
        }
        if ($h['voucher']) {
            $h['voucher']->increment('terpakai');
        }

        return $pesanan;
    }

    /** @return array{0:int,1:int} [kode unik, total] */
    private function unikBebas(array $h, int $unik): array
    {
        $dasar = $h['total'] - $h['kode_unik'];
        $terpakai = Pesanan::where('status', Pesanan::MENUNGGU)->where('kedaluwarsa_at', '>', now())
            ->where('metode_id', $h['metode']->id)->pluck('total')->flip();

        for ($i = 0; $i < 200; $i++) {
            if (! isset($terpakai[$dasar + $unik])) {
                return [$unik, $dasar + $unik];
            }
            $unik = $unik >= HitungPesanan::UNIK_MAKS ? HitungPesanan::UNIK_MIN : $unik + 1;
        }

        throw new PesananGagal('Terlalu banyak pesanan menunggu. Coba lagi beberapa saat.');
    }

    private function nomorBerikut(): string
    {
        $tahun = now()->year;
        $awalan = 'INV-'.$tahun.'-';
        $terakhir = Pesanan::where('nomor', 'like', $awalan.'%')->orderByRaw('length(nomor) desc')->orderByDesc('nomor')->value('nomor');
        $urut = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }
}
