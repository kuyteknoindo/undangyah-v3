<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Artikel/FAQ Pusat Bantuan. `isi` teks biasa (format sederhana lewat TeksBantuan), tidak pernah HTML mentah. */
class BantuanArtikel extends Model
{
    protected $table = 'bantuan_artikel';

    protected $guarded = [];

    /** @var array<string,string> kunci kategori => label */
    public const KATEGORI = [
        'undangan' => 'Undangan',
        'tamu' => 'Tamu & RSVP',
        'akun' => 'Akun',
        'bayar' => 'Pembayaran',
    ];

    protected function casts(): array
    {
        return ['publik' => 'boolean'];
    }

    public function suara(): HasMany
    {
        return $this->hasMany(BantuanSuara::class);
    }

    public function scopePublik(Builder $q): Builder
    {
        return $q->where('publik', true)->orderBy('urutan')->orderBy('id');
    }
}
