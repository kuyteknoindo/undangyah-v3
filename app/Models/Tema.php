<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tema extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tema';

    protected $guarded = [];

    public const TIER = ['Gratis', 'Premium', 'Deluxe'];

    protected function casts(): array
    {
        return ['latar_tetap' => 'array', 'dokumen' => 'array'];
    }

    public function undangan(): HasMany
    {
        return $this->hasMany(Undangan::class, 'tema_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatTema::class, 'tema_id');
    }

    public function scopeTerbit(Builder $q): Builder
    {
        return $q->where('status', 'publish');
    }

    public function scopeUrut(Builder $q): Builder
    {
        return $q->orderBy('urutan')->orderBy('id');
    }
}
