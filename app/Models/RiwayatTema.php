<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatTema extends Model
{
    protected $table = 'riwayat_tema';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'versi' => 'integer'];
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class, 'tema_id')->withTrashed();
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
