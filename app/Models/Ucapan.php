<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ucapan extends Model
{
    use HasFactory;

    protected $table = 'ucapan';

    protected $guarded = [];

    public function undangan(): BelongsTo
    {
        return $this->belongsTo(Undangan::class, 'undangan_id');
    }

    public function tamu(): BelongsTo
    {
        return $this->belongsTo(Tamu::class, 'tamu_id');
    }
}
