<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BantuanSuara extends Model
{
    protected $table = 'bantuan_suara';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['membantu' => 'boolean'];
    }
}
