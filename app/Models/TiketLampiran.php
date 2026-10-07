<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Berkas lampiran satu tiket bantuan. Disimpan di disk privat; tidak ada URL publik. */
class TiketLampiran extends Model
{
    public const DISK = 'local';

    public const MAKS_BERKAS = 3;

    public const MAKS_KB = 2048;

    protected $table = 'tiket_lampiran';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (TiketLampiran $l) {
            Storage::disk(self::DISK)->delete($l->path);
        });
    }

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(TiketBantuan::class, 'tiket_bantuan_id');
    }

    public static function ukuranTeks(int $byte): string
    {
        return $byte >= 1048576
            ? str_replace('.', ',', number_format($byte / 1048576, 1)).' MB'
            : max(1, (int) round($byte / 1024)).' KB';
    }
}
