<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPesanan extends Model
{
    protected $table = 'item_pesanan';

    public $timestamps = false;

    protected $guarded = [];

    public const PAKET = 'paket';

    public const ADDON = 'addon';

    protected function casts(): array
    {
        return ['harga' => 'integer', 'efek' => 'array'];
    }

    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class);
    }

    public function namaTampil(): string
    {
        return $this->tipe === self::ADDON ? 'Add-on: '.$this->nama : $this->nama;
    }
}
