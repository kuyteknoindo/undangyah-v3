<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ucapan extends Model
{
    use HasFactory;

    protected $table = 'ucapan';

    /** Sematan yang tampil di undangan publik (halaman pertama). */
    public const MAKS_SEMATAN = 5;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['disembunyikan' => 'boolean', 'disematkan' => 'boolean'];
    }

    /** Yang boleh dibaca tamu: tidak disembunyikan. */
    public function scopeTampil(Builder $q): Builder
    {
        return $q->where('disembunyikan', false);
    }

    /** Sematan di atas, lalu terbaru. */
    public function scopeUrutPublik(Builder $q): Builder
    {
        return $q->orderByDesc('disematkan')->orderByDesc('id');
    }

    public function undangan(): BelongsTo
    {
        return $this->belongsTo(Undangan::class, 'undangan_id');
    }

    public function tamu(): BelongsTo
    {
        return $this->belongsTo(Tamu::class, 'tamu_id');
    }
}
