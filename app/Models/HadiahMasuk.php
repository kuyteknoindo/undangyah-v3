<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HadiahMasuk extends Model
{
    protected $table = 'hadiah_masuk';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['terkonfirmasi' => 'boolean', 'nominal' => 'integer'];
    }

    public function undangan(): BelongsTo
    {
        return $this->belongsTo(Undangan::class, 'undangan_id');
    }
}
