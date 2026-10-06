<?php

namespace App\Models;

use Database\Factories\NotifikasiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi dalam aplikasi milik satu pengguna. "Hapus" = disembunyikan (disembunyikan_at), bukan dihapus dari tabel.
 * Kirim lewat App\Services\Notifikasi.
 */
class Notifikasi extends Model
{
    /** @use HasFactory<NotifikasiFactory> */
    use HasFactory;

    protected $table = 'notifikasi';

    protected $guarded = [];

    public const TIPE_RSVP = 'rsvp';

    public const TIPE_UCAPAN = 'ucapan';

    public const TIPE_SISTEM = 'sistem';

    public const TIPE_PEMBAYARAN = 'pembayaran';

    public const TIPE_KIRIM = 'kirim';

    /**
     * Tipe => [label, latar, warna ikon, teks tautan, ikon]. Tipe di luar daftar diperlakukan sebagai "sistem".
     *
     * @var array<string, array{0:string,1:string,2:string,3:string,4:string}>
     */
    public const JENIS = [
        self::TIPE_RSVP => ['RSVP', '#E3ECFD', '#1D4ED8', 'Lihat RSVP', 'rsvp'],
        self::TIPE_UCAPAN => ['Ucapan', '#FFE9D6', '#B25E00', 'Buka Buku Tamu', 'buku'],
        self::TIPE_SISTEM => ['Sistem', '#ECEEF1', '#454B55', 'Lihat', 'lonceng'],
        self::TIPE_PEMBAYARAN => ['Pembayaran', '#FFF4CC', '#9A6400', 'Lihat paket', 'billing'],
        self::TIPE_KIRIM => ['Kirim', '#D7FFB8', '#3C7A00', 'Lihat riwayat', 'notif-kirim'],
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'dibaca_at' => 'datetime',
            'disembunyikan_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeTampil(Builder $q): void
    {
        $q->whereNull('disembunyikan_at');
    }

    public function scopeBelumDibaca(Builder $q): void
    {
        $q->whereNull('dibaca_at');
    }

    public function sudahDibaca(): bool
    {
        return $this->dibaca_at !== null;
    }

    /** Tipe yang dikenali (selain itu jadi "sistem"). */
    public static function tipeValid(string $tipe): string
    {
        return isset(self::JENIS[$tipe]) ? $tipe : self::TIPE_SISTEM;
    }

    /** @return array{0:string,1:string,2:string,3:string,4:string} */
    public function jenis(): array
    {
        return self::JENIS[self::tipeValid($this->tipe)];
    }

    /** Tautan yang aman dipakai sebagai href: hanya jalur relatif sesitus atau http(s). */
    public static function tautanAman(?string $tautan): ?string
    {
        $tautan = trim((string) $tautan);
        if ($tautan === '') {
            return null;
        }
        // Garis miring terbalik, spasi, dan karakter kontrol dibaca peramban sebagai "//" (alihan ke situs lain).
        if (preg_match('/[\s\x00-\x1F\x7F\\\\]/', $tautan)) {
            return null;
        }
        if (preg_match('#^/(?!/)#', $tautan) || preg_match('#^https?://#i', $tautan)) {
            return $tautan;
        }

        return null;
    }
}
