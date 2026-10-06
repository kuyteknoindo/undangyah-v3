<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Tamu extends Model
{
    use HasFactory;

    protected $table = 'tamu';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['dikirim_at' => 'datetime', 'jumlah' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Tamu $tamu) {
            $tamu->kode ??= static::kodeBaru();
        });
    }

    /** Token pendek acak untuk tautan pribadi (huruf kecil + angka, tanpa karakter yang mirip). */
    public static function kodeBaru(): string
    {
        do {
            $kode = Str::lower(Str::random(10));
            $kode = strtr($kode, ['l' => 'k', 'o' => 'p', '0' => '7', '1' => '8']);
        } while (static::where('kode', $kode)->exists());

        return $kode;
    }

    public function undangan(): BelongsTo
    {
        return $this->belongsTo(Undangan::class, 'undangan_id');
    }
}
