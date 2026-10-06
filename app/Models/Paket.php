<?php

namespace App\Models;

use App\Services\SinkronKuotaPaket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Paket langganan (katalog). Pengguna menyimpan kode paketnya di `users.paket`. */
class Paket extends Model
{
    protected $table = 'paket';

    protected $guarded = [];

    public const AKTIF = 'aktif';

    public const DRAF = 'draf';

    public const ARSIP = 'arsip';

    protected static function booted(): void
    {
        // Kuota yang dibaca KuotaUndangan/KuotaTamu berasal dari Pengaturan; jaga agar selalu mengikuti katalog (juga saat admin mengubahnya).
        static::saved(fn () => app(SinkronKuotaPaket::class)->sinkronkan());
        static::deleted(fn () => app(SinkronKuotaPaket::class)->sinkronkan());
    }

    protected function casts(): array
    {
        return [
            'harga' => 'integer',
            'harga_coret' => 'integer',
            'durasi_bulan' => 'integer',
            'kuota_undangan' => 'integer',
            'kuota_tamu' => 'integer',
            'kuota_foto' => 'integer',
            'fitur' => 'array',
            'populer' => 'boolean',
        ];
    }

    /** Paket yang tampil di halaman Paket & Billing (tanpa arsip). */
    public function scopeTampil(Builder $q): void
    {
        $q->where('status', '!=', self::ARSIP)->orderBy('urutan')->orderBy('id');
    }

    public function dapatDibeli(): bool
    {
        return $this->status === self::AKTIF && $this->harga > 0;
    }
}
