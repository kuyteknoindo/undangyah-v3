<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Undangan extends Model
{
    use HasFactory;

    protected $table = 'undangan';

    protected $guarded = [];

    public const DRAF = 'draf';

    public const TERBIT = 'terbit';

    protected function casts(): array
    {
        return [
            'isi' => 'array',
            'bagian' => 'array',
            'pengaturan' => 'array',
            'terbitan' => 'array',
            'langkah' => 'array',
            'diterbitkan_at' => 'datetime',
            'draf_disimpan_at' => 'datetime',
            'tanggal_acara' => 'date',
            'ada_perubahan' => 'boolean',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class, 'tema_id')->withTrashed();
    }

    public function tamu(): HasMany
    {
        return $this->hasMany(Tamu::class, 'undangan_id');
    }

    public function rsvp(): HasMany
    {
        return $this->hasMany(Rsvp::class, 'undangan_id');
    }

    public function ucapan(): HasMany
    {
        return $this->hasMany(Ucapan::class, 'undangan_id');
    }

    public function tayangan(): HasMany
    {
        return $this->hasMany(Tayangan::class, 'undangan_id');
    }

    public function sudahTerbit(): bool
    {
        return $this->status === self::TERBIT;
    }
}
