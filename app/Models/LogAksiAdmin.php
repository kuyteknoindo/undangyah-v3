<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan aksi admin atas akun pengguna (peran, paket manual, tangguh, hapus, masuk sebagai). Tidak pernah memuat rahasia. */
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
            'created_at' => now(),
        ]);
    }
}
