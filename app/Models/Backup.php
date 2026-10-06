<?php

namespace App\Models;

use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan satu backup (database/file/penuh). Isi berkasnya ada di disk privat, bukan di tabel ini. */
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory;

    protected $table = 'backup';

    protected $guarded = [];

    public const TIPE = ['database' => 'Database', 'file' => 'File unggahan', 'penuh' => 'Penuh (database + file)'];

    public const BERJALAN = 'berjalan';

    public const SUKSES = 'sukses';

    public const GAGAL = 'gagal';

    public const STATUS = [
        self::BERJALAN => ['biru', 'Berjalan'],
        self::SUKSES => ['hijau', 'Sukses'],
        self::GAGAL => ['merah', 'Gagal'],
    ];

    protected function casts(): array
    {
        return [
            'ukuran' => 'integer',
            'verifikasi' => 'array',
            'uji_pulih' => 'array',
            'diverifikasi_at' => 'datetime',
            'uji_pulih_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function sukses(): bool
    {
        return $this->status === self::SUKSES;
    }

    /** Hanya backup database (atau penuh) berformat pg_dump custom yang bisa diuji pulih. */
    public function bisaDiujiPulih(): bool
    {
        return $this->sukses() && in_array($this->tipe, ['database', 'penuh'], true) && in_array($this->format, ['pgdump-custom', 'tar'], true);
    }
}
