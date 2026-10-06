<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonPengguna extends Model
{
    protected $table = 'addon_pengguna';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['jumlah' => 'integer'];
    }

    /** @return array<string,int> kode add-on => jumlah dimiliki */
    public static function jumlahMilik(int $userId): array
    {
        return static::where('user_id', $userId)->pluck('jumlah', 'addon_kode')->map(fn ($j) => (int) $j)->all();
    }
}
