<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AddOn extends Model
{
    protected $table = 'addon';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'harga' => 'integer',
            'multi' => 'boolean',
            'butuh_premium' => 'boolean',
            'termasuk_premium' => 'boolean',
            'efek' => 'array',
            'aktif' => 'boolean',
        ];
    }

    public function scopeTersedia(Builder $q): void
    {
        $q->where('aktif', true)->orderBy('urutan')->orderBy('id');
    }

    public function hargaTampil(): string
    {
        return 'Rp '.number_format($this->harga, 0, ',', '.').($this->satuan ?? '');
    }
}
