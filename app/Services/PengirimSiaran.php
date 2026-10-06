<?php

namespace App\Services;

use App\Jobs\KirimSiaranNotifikasi;
use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\SiaranNotifikasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Siaran notifikasi admin ke segmen pengguna. Tidak pernah mengirim dalam satu permintaan besar: siaran dicatat,
 * lalu diproses per potongan (UKURAN_POTONGAN pengguna) oleh KirimSiaranNotifikasi. Tiap potongan satu transaksi
 * dan menyimpan kursor, jadi diulang/dilanjutkan tidak menggandakan notifikasi.
 */
class PengirimSiaran
{
    public const UKURAN_POTONGAN = 500;

    /** Tipe notifikasi yang boleh dipakai siaran (Notifikasi::JENIS). */
    public const TIPE = [ModelNotifikasi::TIPE_SISTEM, ModelNotifikasi::TIPE_PEMBAYARAN];

    public function __construct(private Notifikasi $notifikasi, private SegmenPengguna $segmen) {}

    public function hitungPenerima(string $paket, string $peran): int
    {
        return $this->segmen->penerima($paket, $peran)->count();
    }

    /** @param  array{tipe:string,judul:string,isi:string,tautan:?string,paket:string,peran:string}  $data */
    public function buat(User $admin, array $data): SiaranNotifikasi
    {
        abort_unless($admin->isAdmin(), 403);

        $siaran = SiaranNotifikasi::create([
            'admin_id' => $admin->id,
            'tipe' => in_array($data['tipe'], self::TIPE, true) ? $data['tipe'] : ModelNotifikasi::TIPE_SISTEM,
            'judul' => mb_substr($data['judul'], 0, 60),
            'isi' => mb_substr($data['isi'], 0, 300),
            'tautan' => ModelNotifikasi::tautanAman($data['tautan'] ?? null),
            'target_paket' => $data['paket'],
            'target_peran' => $data['peran'],
            'jumlah_target' => $this->hitungPenerima($data['paket'], $data['peran']),
            'status' => SiaranNotifikasi::ANTRE,
        ]);

        KirimSiaranNotifikasi::dispatch($siaran->id);

        return $siaran;
    }

    /**
     * Proses satu potongan. Mengembalikan true bila siaran sudah selesai (atau tidak perlu diproses lagi).
     */
    public function proses(int $siaranId, int $ukuran = self::UKURAN_POTONGAN): bool
    {
        return DB::transaction(function () use ($siaranId, $ukuran) {
            $s = SiaranNotifikasi::lockForUpdate()->find($siaranId);
            if (! $s || ! $s->belumSelesai()) {
                return true;
            }

            $penerima = $this->segmen->penerima($s->target_paket, $s->target_peran)
                ->where('id', '>', $s->kursor_user_id)->orderBy('id')->limit($ukuran)->get(['id']);

            foreach ($penerima as $u) {
                $this->notifikasi->kirim($u->id, $s->tipe, $s->judul, $s->isi, $s->tautan, ['siaran_id' => $s->id]);
            }

            $selesai = $penerima->count() < $ukuran;
            $s->forceFill([
                'status' => $selesai ? SiaranNotifikasi::SELESAI : SiaranNotifikasi::BERJALAN,
                'terkirim' => $s->terkirim + $penerima->count(),
                'kursor_user_id' => $penerima->isEmpty() ? $s->kursor_user_id : (int) $penerima->last()->id,
                'mulai_at' => $s->mulai_at ?? now(),
                'selesai_at' => $selesai ? now() : null,
            ])->save();

            return $selesai;
        });
    }

    /** Proses terus sampai selesai atau waktu habis (tombol "Proses sekarang" bila tak ada pekerja antrean). */
    public function prosesSampaiSelesai(int $siaranId, int $detikMaks = 20): bool
    {
        $batas = microtime(true) + $detikMaks;
        do {
            if ($this->proses($siaranId)) {
                return true;
            }
        } while (microtime(true) < $batas);

        return false;
    }

    public function tandaiGagal(int $siaranId): void
    {
        SiaranNotifikasi::whereKey($siaranId)->whereIn('status', [SiaranNotifikasi::ANTRE, SiaranNotifikasi::BERJALAN])
            ->update(['status' => SiaranNotifikasi::GAGAL, 'selesai_at' => now()]);
    }
}
