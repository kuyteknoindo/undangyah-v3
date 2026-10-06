<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MetodePembayaran extends Model
{
    protected $table = 'metode_pembayaran';

    protected $guarded = [];

    public const MANUAL = 'manual';

    public const INSTRUKSI = 'instruksi';

    protected function casts(): array
    {
        return [
            'biaya_tetap' => 'integer',
            'biaya_persen' => 'float',
            'kode_unik' => 'boolean',
            'data' => 'array',
            'aktif' => 'boolean',
        ];
    }

    public function scopeTersedia(Builder $q): void
    {
        $q->where('aktif', true)->orderBy('urutan')->orderBy('id');
    }

    /** Biaya pembayaran atas jumlah setelah diskon; 0 bila tidak ada tagihan. */
    public function biayaUntuk(int $dasar): int
    {
        if ($dasar <= 0) {
            return 0;
        }

        return $this->biaya_tetap + (int) round($dasar * $this->biaya_persen / 100);
    }

    public function teksBiaya(): string
    {
        $bagian = [];
        if ($this->biaya_persen > 0) {
            $bagian[] = rtrim(rtrim(number_format($this->biaya_persen, 2, ',', ''), '0'), ',').'%';
        }
        if ($this->biaya_tetap > 0) {
            $bagian[] = 'Rp '.number_format($this->biaya_tetap, 0, ',', '.');
        }

        return $bagian ? 'Biaya '.implode(' + ', $bagian) : 'Tanpa biaya';
    }
}
