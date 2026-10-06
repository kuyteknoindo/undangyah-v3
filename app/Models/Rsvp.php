<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rsvp extends Model
{
    use HasFactory;

    protected $table = 'rsvp';

    protected $guarded = [];

    public const HADIR = 'hadir';

    public const TIDAK = 'tidak';

    public function undangan(): BelongsTo
    {
        return $this->belongsTo(Undangan::class, 'undangan_id');
    }

    public function tamu(): BelongsTo
    {
        return $this->belongsTo(Tamu::class, 'tamu_id');
    }
}
