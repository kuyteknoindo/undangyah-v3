<?php

namespace App\Livewire\Concerns;

use App\Models\LogAksiAdmin;
use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\Pesanan;
use App\Models\User;
use App\Services\KonfirmasiPesanan;
use App\Services\Notifikasi;
use App\Services\TampilPesananAdmin;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;

/**
 * Aksi admin pada satu pesanan (lunas, tolak bukti, batal, kedaluwarsa, refund, pending, kirim ulang tagihan) beserta
 * dialog konfirmasinya. Semua perubahan status lewat KonfirmasiPesanan; di sini hanya otorisasi, validasi, dan umpan balik.
 * Setiap aksi memeriksa peran admin ulang karena panggilan Livewire bisa dikirim klien secara langsung.
 */
trait AksiPesananAdmin
{
    use MintaKonfirmasiSandi;

    #[Locked]
    public ?int $aksiId = null;

    #[Locked]
    public string $aksiJenis = '';

    public string $alasan = '';

    public bool $turunkan = false;

    public bool $dialogAksi = false;

    /** Jenis aksi => [judul, label tombol, wajib alasan]. */
    protected const AKSI = [
        'lunas' => ['Tandai pesanan lunas?', 'Tandai lunas', false],
        'tolak' => ['Tolak bukti pembayaran?', 'Tolak bukti', true],
        'batal' => ['Batalkan pesanan?', 'Batalkan pesanan', true],
        'kedaluwarsa' => ['Tandai pesanan kedaluwarsa?', 'Tandai kedaluwarsa', false],
        'refund' => ['Tandai pesanan refund?', 'Tandai refund', true],
        'tinjau' => ['Tandai selesai ditinjau?', 'Tandai selesai', false],
    ];

    protected function pastikanAdmin(): User
    {
        $user = auth()->user();
        abort_unless($user?->isAdmin(), 403);

        return $user;
    }

    protected function pesananAdmin(int $id): Pesanan
    {
        $this->pastikanAdmin();

        return Pesanan::with('item')->findOrFail($id);
    }

    /** Aksi yang boleh dilakukan pada status SAAT INI (dibaca dari DB, bukan dari klien). @return list<string> */
    protected function aksiBoleh(Pesanan $p): array
    {
        return match ($p->status) {
            Pesanan::MENUNGGU => array_values(array_filter(['lunas', $p->bukti_path ? 'tolak' : null, 'batal', 'kedaluwarsa'])),
            Pesanan::KEDALUWARSA => ['lunas'],
            Pesanan::LUNAS => $p->perluDitinjau() ? ['tinjau', 'refund'] : ['refund'],
            default => [],
        };
    }

    public function tanya(int $id, string $jenis): void
    {
        $p = $this->pesananAdmin($id);
        if (! isset(self::AKSI[$jenis]) || ! in_array($jenis, $this->aksiBoleh($p), true)) {
            $this->dispatch('toast', pesan: 'Aksi ini tidak tersedia untuk status pesanan sekarang.', tipe: 'galat');

            return;
        }
        $this->resetErrorBag();
        $this->aksiId = $p->id;
        $this->aksiJenis = $jenis;
        $this->alasan = '';
        $this->turunkan = $jenis === 'refund' && ! $p->perluDitinjau() && $p->item->contains('tipe', 'paket');
        $this->dialogAksi = true;
    }

    public function jalankan(KonfirmasiPesanan $konfirmasi): void
    {
        $admin = $this->pastikanAdmin();
        $jenis = $this->aksiJenis;
        if (! $this->aksiId || ! isset(self::AKSI[$jenis])) {
            return;
        }
        $wajib = self::AKSI[$jenis][2];
        $this->validate(
            ['alasan' => [$wajib ? 'required' : 'nullable', 'string', 'min:3', 'max:300']],
            ['alasan.required' => 'Alasan wajib diisi.', 'alasan.min' => 'Alasan minimal 3 karakter.', 'alasan.max' => 'Alasan maksimal 300 karakter.'],
        );

        $p = $this->pesananAdmin($this->aksiId);
        $alasan = trim($this->alasan);
        if (! in_array($jenis, $this->aksiBoleh($p), true)) {
            $this->selesaiAksi();
            $this->dispatch('toast', pesan: 'Status pesanan sudah berubah. Tidak ada yang diubah.', tipe: 'galat');

            return;
        }

        // Refund memengaruhi uang dan akses pembeli: wajib konfirmasi sandi admin (dicek di sini, bukan hanya di tombol).
        if ($jenis === 'refund' && ! $this->butuhSandi('Tandai pesanan refund', 'jalankan')) {
            return;
        }

        $berubah = match ($jenis) {
            'lunas' => $konfirmasi->lunas($p, $alasan !== '' ? $alasan : null),
            'tolak' => $konfirmasi->tolak($p, $alasan),
            'batal' => $konfirmasi->tolak($p, $alasan, true),
            'kedaluwarsa' => $konfirmasi->kedaluwarsakan($p, true),
            'refund' => $konfirmasi->refund($p, $alasan, $this->turunkan),
            'tinjau' => $konfirmasi->selesaiTinjau($p, $alasan !== '' ? $alasan : null),
            default => false,
        };

        $this->selesaiAksi();
        if (! $berubah) {
            $this->dispatch('toast', pesan: 'Status pesanan sudah berubah. Tidak ada yang diubah.', tipe: 'galat');

            return;
        }
        // Aksi bernilai uang harus punya jejak siapa pelakunya (catatan_admin pesanan tidak menyimpan admin).
        LogAksiAdmin::catat($admin, 'pesanan_'.$jenis, User::find($p->user_id), ['nomor' => $p->nomor, 'total' => $p->total, 'alasan' => $alasan !== '' ? $alasan : null]);
        $this->dispatch('toast', pesan: match ($jenis) {
            'lunas' => 'Pesanan '.$p->nomor.' ditandai lunas dan diaktifkan.',
            'tolak' => 'Bukti pesanan '.$p->nomor.' ditolak. Pembeli diminta mengunggah ulang.',
            'batal' => 'Pesanan '.$p->nomor.' dibatalkan.',
            'kedaluwarsa' => 'Pesanan '.$p->nomor.' ditandai kedaluwarsa.',
            'tinjau' => 'Pesanan '.$p->nomor.' ditandai selesai ditinjau.',
            default => 'Pesanan '.$p->nomor.' ditandai refund.',
        }, tipe: 'sukses');
        $this->setelahAksi($p->id);
    }

    public function batalAksi(): void
    {
        $this->selesaiAksi();
    }

    /** Pending: penanda bahwa bukti belum bisa diputuskan. Hanya untuk pesanan yang menunggu dan sudah punya bukti. */
    public function tunda(int $id): void
    {
        $p = $this->pesananAdmin($id);
        if ($p->status !== Pesanan::MENUNGGU || ! $p->bukti_path) {
            $this->dispatch('toast', pesan: 'Hanya pesanan menunggu yang sudah punya bukti yang bisa di-Pending.', tipe: 'galat');

            return;
        }
        $p->forceFill(['ditunda_at' => $p->ditunda_at ? null : now()])->save();
        $this->dispatch('toast', pesan: $p->ditunda_at ? 'Pesanan '.$p->nomor.' ditandai Pending.' : 'Tanda Pending dilepas.', tipe: 'info');
    }

    /** Kirim ulang tagihan/invoice sebagai notifikasi dalam aplikasi ke pembeli; satu kali per menit per pesanan. */
    public function kirimUlang(int $id, Notifikasi $notifikasi): void
    {
        $admin = $this->pastikanAdmin();
        $p = $this->pesananAdmin($id);
        if (in_array($p->status, [Pesanan::BATAL, Pesanan::REFUND], true)) {
            $this->dispatch('toast', pesan: 'Pesanan batal atau refund tidak dikirim ulang.', tipe: 'galat');

            return;
        }
        $kunci = 'kirim-ulang-pesanan:'.$p->id;
        if (RateLimiter::tooManyAttempts($kunci, 1)) {
            $this->dispatch('toast', pesan: 'Tagihan baru saja dikirim. Coba lagi sebentar lagi.', tipe: 'info');

            return;
        }
        RateLimiter::hit($kunci, 60);

        $status = $p->statusEfektif();
        [$judul, $isi, $tautan] = match ($status) {
            Pesanan::LUNAS => ['Invoice '.$p->nomor, 'Invoice pesanan '.$p->nomor.' ('.TampilPesananAdmin::rp($p->total).') sudah lunas. Buka untuk melihat atau mencetaknya.', '/invoice/'.$p->nomor],
            Pesanan::MENUNGGU => ['Pengingat tagihan '.$p->nomor, 'Tagihan '.TampilPesananAdmin::rp($p->total).' menunggu pembayaran sampai '.TampilPesananAdmin::tgl($p->kedaluwarsa_at).'. Selesaikan transfer dan unggah buktinya.', '/konfirmasi-pembayaran/'.$p->nomor],
            default => ['Tagihan '.$p->nomor.' kedaluwarsa', 'Waktu pembayaran tagihan '.$p->nomor.' sudah habis. Buat pesanan baru bila masih ingin membeli.', '/billing'],
        };
        $notifikasi->kirim($p->user_id, ModelNotifikasi::TIPE_PEMBAYARAN, $judul, $isi, $tautan, ['pesanan' => $p->nomor, 'dikirim_oleh' => $admin->id]);
        $this->dispatch('toast', pesan: 'Tagihan '.$p->nomor.' dikirim ulang ke '.$p->pembeli_email.' (notifikasi dalam aplikasi).', tipe: 'sukses');
    }

    private function selesaiAksi(): void
    {
        $this->dialogAksi = false;
        $this->aksiId = null;
        $this->aksiJenis = '';
        $this->alasan = '';
        $this->turunkan = false;
        $this->resetErrorBag();
    }

    /** Titik kait untuk halaman yang perlu membereskan keadaan setelah aksi berhasil. */
    protected function setelahAksi(int $pesananId): void {}

    /**
     * Data untuk dialog konfirmasi, atau null bila tidak ada aksi berjalan.
     *
     * @return array<string,mixed>|null
     */
    protected function dataKonfirmasi(): ?array
    {
        if (! $this->aksiId || ! isset(self::AKSI[$this->aksiJenis])) {
            return null;
        }
        $p = Pesanan::with('item')->find($this->aksiId);
        if (! $p) {
            return null;
        }
        [$judul, $tombol, $wajib] = self::AKSI[$this->aksiJenis];
        $peringatan = null;
        if ($this->aksiJenis === 'lunas') {
            if ($p->status === Pesanan::KEDALUWARSA || $p->statusEfektif() === Pesanan::KEDALUWARSA) {
                $peringatan = 'Pesanan ini sudah kedaluwarsa. Menandai lunas tetap mengaktifkannya; pastikan uang sudah benar-benar masuk.';
            } elseif (! $p->bukti_path) {
                $peringatan = 'Pembeli belum mengunggah bukti. Pastikan dana sudah masuk di mutasi rekening sebelum menandai lunas.';
            }
            if ($bentrok = TampilPesananAdmin::bentrokPaket($p)) {
                $peringatan = trim(($peringatan ? $peringatan.' ' : '').$bentrok);
            }
        }

        return [
            'judul' => $judul,
            'tombol' => $tombol,
            'wajib' => $wajib,
            'jenis' => $this->aksiJenis,
            'sub' => $p->nomor.' · '.$p->pembeli_nama.' · '.TampilPesananAdmin::rp($p->total),
            'efek' => $this->aksiJenis === 'lunas' ? TampilPesananAdmin::efekLunas($p) : [],
            'peringatan' => $peringatan,
            'bisaTurunkan' => $this->aksiJenis === 'refund' && ! $p->perluDitinjau() && $p->item->contains('tipe', 'paket'),
            'label' => match ($this->aksiJenis) {
                'tolak' => 'Alasan penolakan (dikirim ke pembeli)',
                'batal' => 'Alasan pembatalan (dikirim ke pembeli)',
                'refund' => 'Alasan refund (dikirim ke pembeli)',
                'tinjau' => 'Catatan penyelesaian (opsional, mis. dana sudah dikembalikan)',
                default => 'Catatan (opsional)',
            },
        ];
    }
}
