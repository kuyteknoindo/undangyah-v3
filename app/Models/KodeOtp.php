<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class KodeOtp extends Model
{
    use Prunable;

    protected $table = 'otp_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** Baris yang sudah lewat 1 hari dari kedaluwarsa dihapus otomatis (model:prune). */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }
}
