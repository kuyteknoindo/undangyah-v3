<?php

namespace App\Models;

use Database\Factories\PesananFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pesanan extends Model
{
    /** @use HasFactory<PesananFactory> */
    use HasFactory;

    protected $table = 'pesanan';

    protected $guarded = [];

    public const MENUNGGU = 'menunggu';

    public const LUNAS = 'lunas';

    public const KEDALUWARSA = 'kedaluwarsa';

    public const BATAL = 'batal';

    public const REFUND = 'refund';

    /** Status => [label, warna x-pill]. */
    public const STATUS = [
        self::MENUNGGU => ['Menunggu', 'kuning'],
        self::LUNAS => ['Lunas', 'hijau'],
        self::KEDALUWARSA => ['Kedaluwarsa', 'abu'],
        self::BATAL => ['Dibatalkan', 'merah'],
        self::REFUND => ['Dikembalikan', 'abu'],
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'diskon' => 'integer',
            'biaya' => 'integer',
            'kode_unik' => 'integer',
            'total' => 'integer',
            'voucher_ditahan' => 'boolean',
            'metode_data' => 'array',
            'kedaluwarsa_at' => 'datetime',
            'dibayar_at' => 'datetime',
            'dibatalkan_at' => 'datetime',
            'bukti_at' => 'datetime',
            'bukti_ditolak_at' => 'datetime',
        ];
    }

    /** Rute memakai nomor invoice (INV-2026-0001), bukan id berurutan. */
    public function getRouteKeyName(): string
    {
        return 'nomor';
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function metode(): BelongsTo
    {
        return $this->belongsTo(MetodePembayaran::class, 'metode_id');
    }

    public function item(): HasMany
    {
        return $this->hasMany(ItemPesanan::class)->orderBy('urutan')->orderBy('id');
    }

    public function scopeMilik(Builder $q, int|User $user): void
    {
        $q->where('user_id', $user instanceof User ? $user->id : $user);
    }

    /** Pesanan menunggu yang lewat batas waktu tetap berstatus `menunggu` di DB sampai disapu perintah terjadwal; tampilan memakai ini. */
    public function statusEfektif(): string
    {
        if ($this->status === self::MENUNGGU && $this->kedaluwarsa_at && $this->kedaluwarsa_at->isPast()) {
            return self::KEDALUWARSA;
        }

        return $this->status;
    }

    public function menunggu(): bool
    {
        return $this->statusEfektif() === self::MENUNGGU;
    }

    /** @return array{0:string,1:string} [label, warna] */
    public function infoStatus(): array
    {
        return self::STATUS[$this->statusEfektif()] ?? [ucfirst($this->status), 'abu'];
    }

    public function ringkasItem(): string
    {
        return $this->item->map(fn (ItemPesanan $i) => $i->namaTampil())->implode(' + ');
    }

    public function adaPaket(): bool
    {
        return $this->item->contains('tipe', ItemPesanan::PAKET);
    }
}
