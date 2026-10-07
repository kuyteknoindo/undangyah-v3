<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Ringkasan "apa yang dikenai aksi" untuk kolom Sasaran di halaman Log Audit (dan pencariannya). Hanya nilai yang
     * memang tidak sensitif: nomor pesanan, email akun sasaran, kode/nama katalog, id backup/siaran.
     *
     * @param  array<string,mixed>|null  $rincian
     */
    public static function turunkanSasaran(string $aksi, ?string $targetEmail, ?array $rincian): ?string
    {
        $rincian ??= [];
        $teks = fn (string $kunci): ?string => isset($rincian[$kunci]) && is_scalar($rincian[$kunci]) && trim((string) $rincian[$kunci]) !== '' ? trim((string) $rincian[$kunci]) : null;

        $hasil = match (true) {
            $teks('nomor') !== null => 'Pesanan '.$teks('nomor'),
            $targetEmail !== null && $targetEmail !== '' => $targetEmail,
            $teks('metode') !== null => 'Metode '.$teks('metode'),
            $teks('addon') !== null => 'Add-on '.$teks('addon'),
            $teks('paket') !== null && ! is_array($rincian['paket']) && str_starts_with($aksi, 'paket_') => 'Paket '.$teks('paket'),
            $teks('kode') !== null && str_starts_with($aksi, 'voucher_') => 'Voucher '.$teks('kode'),
            $teks('backup_id') !== null => 'Backup #'.$teks('backup_id'),
            $teks('siaran_id') !== null => 'Siaran #'.$teks('siaran_id'),
            $teks('untuk') !== null => $teks('untuk'),
            default => null,
        };

        return $hasil === null ? null : Str::limit($hasil, 190, '');
    }

    /** @param  array<string,mixed>  $rincian */
    public static function catat(User $admin, string $aksi, ?User $target, array $rincian = []): self
    {
        return static::create([
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'target_id' => $target?->id,
            'target_email' => $target?->email,
            'sasaran' => static::turunkanSasaran($aksi, $target?->email, $rincian ?: null),
            'aksi' => $aksi,
            'rincian' => $rincian ?: null,
            'ip' => request()?->ip(),
            'agen' => ($ua = request()?->userAgent()) ? Str::limit(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $ua) ?? '', 160, '') : null,
            'created_at' => now(),
        ]);
    }
}
