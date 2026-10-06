<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiaranNotifikasi extends Model
{
    protected $table = 'siaran_notifikasi';

    protected $guarded = [];

    public const ANTRE = 'antre';

    public const BERJALAN = 'berjalan';

    public const SELESAI = 'selesai';

    public const GAGAL = 'gagal';

    /** Status => [label, warna x-pill]. */
    public const STATUS = [
        self::ANTRE => ['Antre', 'kuning'],
        self::BERJALAN => ['Berjalan', 'biru'],
        self::SELESAI => ['Terkirim', 'hijau'],
        self::GAGAL => ['Gagal', 'merah'],
    ];

    protected function casts(): array
    {
        return [
            'jumlah_target' => 'integer',
            'terkirim' => 'integer',
            'kursor_user_id' => 'integer',
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function belumSelesai(): bool
    {
        return in_array($this->status, [self::ANTRE, self::BERJALAN], true);
    }

    /** @return array{0:string,1:string} */
    public function infoStatus(): array
    {
        return self::STATUS[$this->status] ?? [ucfirst($this->status), 'abu'];
    }
}
