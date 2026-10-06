<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlugDitahan extends Model
{
    protected $table = 'slug_ditahan';

    protected $guarded = [];

    public const GANTI = 'ganti';

    public const HAPUS = 'hapus';

    protected function casts(): array
    {
        return ['ditahan_sampai' => 'datetime'];
    }

    public function scopeAktif($q)
    {
        return $q->where('ditahan_sampai', '>', now());
    }
}
