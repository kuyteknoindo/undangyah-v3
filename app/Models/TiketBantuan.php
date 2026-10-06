<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pesan pengguna ke layanan pelanggan. Balasan admin belum ada (tertunda); status diubah admin lewat database. */
class TiketBantuan extends Model
{
    protected $table = 'tiket_bantuan';

    protected $guarded = [];

    /** @var array<string,string> */
    public const KATEGORI = [
        'undangan' => 'Undangan dan desain',
        'tamu' => 'Tamu dan RSVP',
        'akun' => 'Akun dan keamanan',
        'bayar' => 'Paket dan pembayaran',
        'lain' => 'Lainnya',
    ];

    /** @var array<string,array{0:string,1:string}> status => [label, warna pill] */
    public const STATUS = [
        'diproses' => ['Diproses', 'kuning'],
        'selesai' => ['Selesai', 'hijau'],
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function undangan(): BelongsTo
    {
        return $this->belongsTo(Undangan::class);
    }

    public function labelKategori(): string
    {
        return self::KATEGORI[$this->kategori] ?? 'Lainnya';
    }

    /** @return array{0:string,1:string} */
    public function status(): array
    {
        return self::STATUS[$this->status] ?? self::STATUS['diproses'];
    }
}
