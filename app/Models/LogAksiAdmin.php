<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Jejak audit aksi admin: akun pengguna (peran, paket manual, tangguh, hapus, masuk sebagai) dan aksi sensitif lain
 * (rekening, backup, ekspor, konfirmasi sandi). Memuat IP dan agen-pengguna ringkas; TIDAK PERNAH memuat kata sandi atau rahasia.
 */
class LogAksiAdmin extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'log_aksi_admin';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rincian' => 'array', 'created_at' => 'datetime'];
    }

    /** @param  array<string,mixed>  $rincian */
    public static function catat(User $admin, string $aksi, ?User $target, array $rincian = []): self
    {
        return static::create([
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'target_id' => $target?->id,
            'target_email' => $target?->email,
            'aksi' => $aksi,
            'rincian' => $rincian ?: null,
            'ip' => request()?->ip(),
            'agen' => ($ua = request()?->userAgent()) ? Str::limit(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $ua) ?? '', 160, '') : null,
            'created_at' => now(),
        ]);
    }
}
