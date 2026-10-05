<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Pengaturan sistem: Pengaturan::ambil('pemeliharaan.aktif', false), Pengaturan::simpan('pemeliharaan', [...]). */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['nilai' => 'array'];
    }

    /** Ambil nilai; kunci bertitik membaca bagian dalam, mis. 'pemeliharaan.aktif'. */
    public static function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        [$akar, $sisa] = array_pad(explode('.', $kunci, 2), 2, null);
        $nilai = Cache::remember('pengaturan:'.$akar, 300, fn () => static::find($akar)?->nilai);

        if ($nilai === null) {
            return $bawaan;
        }

        return $sisa === null ? $nilai : data_get($nilai, $sisa, $bawaan);
    }

    public static function simpan(string $kunci, array $nilai): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        Cache::forget('pengaturan:'.$kunci);
    }
}
