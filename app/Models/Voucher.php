<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $table = 'voucher';

    protected $guarded = [];

    public const PERSEN = 'persen';

    public const NOMINAL = 'nominal';

    protected function casts(): array
    {
        return [
            'nilai' => 'integer',
            'maks_diskon' => 'integer',
            'min_belanja' => 'integer',
            'batas_pakai' => 'integer',
            'terpakai' => 'integer',
            'berlaku_dari' => 'datetime',
            'berlaku_sampai' => 'datetime',
            'paket_berlaku' => 'array',
            'aktif' => 'boolean',
        ];
    }

    public static function normalisasi(string $kode): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($kode)) ?? '');
    }

    public function habis(): bool
    {
        return $this->batas_pakai !== null && $this->terpakai >= $this->batas_pakai;
    }

    /** aktif | nonaktif | habis | kedaluwarsa | belum (belum berlaku). */
    public function status(): string
    {
        return match (true) {
            ! $this->aktif => 'nonaktif',
            $this->berlaku_sampai && $this->berlaku_sampai->isPast() => 'kedaluwarsa',
            $this->berlaku_dari && $this->berlaku_dari->isFuture() => 'belum',
            $this->habis() => 'habis',
            default => 'aktif',
        };
    }
}
