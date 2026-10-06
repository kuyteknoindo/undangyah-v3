<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu berkas di pustaka media. user_id kosong = aset admin/sistem. Berkas (dan thumbnail) di disk `media` ikut terhapus
 * saat record dihapus lewat model (jangan memakai query massal `->delete()` agar berkas tidak tertinggal).
 */
class Media extends Model
{
    protected $table = 'media';

    protected $guarded = [];

    public const DISK = 'media';

    public const GAMBAR = 'gambar';

    public const AUDIO = 'audio';

    public const VIDEO = 'video';

    public const DOKUMEN = 'dokumen';

    public const TIPE = [
        self::GAMBAR => 'Gambar',
        self::AUDIO => 'Audio',
        self::VIDEO => 'Video',
        self::DOKUMEN => 'Dokumen',
    ];

    public const FOLDER = ['Umum', 'Foto', 'Ornamen', 'Template', 'Musik', 'Video', 'Dokumen'];

    protected function casts(): array
    {
        return [
            'ukuran' => 'integer',
            'lebar' => 'integer',
            'tinggi' => 'integer',
            'dipakai' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $media) {
            $disk = Storage::disk(self::DISK);
            $disk->delete(array_filter([$media->path, $media->thumb_path]));
        });
    }

    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Milik satu pengguna; null = aset sistem. */
    public function scopeMilik(Builder $q, ?User $user): void
    {
        $user === null ? $q->whereNull('user_id') : $q->where('user_id', $user->id);
    }

    /** URL berkas (path situs, mis. /storage/media/u3/2026/10/abc.jpg). Aman dipakai di `_foto` dan musik undangan. */
    public function url(): string
    {
        return self::urlDari($this->path);
    }

    /** Dibangun dari konfigurasi disk (bukan Storage::url) agar sama di produksi dan di tes yang memakai Storage::fake. */
    private static function urlDari(string $path): string
    {
        return rtrim((string) config('filesystems.disks.'.self::DISK.'.url', '/storage/media'), '/').'/'.ltrim($path, '/');
    }

    /** URL thumbnail; gambar tanpa thumbnail memakai berkas aslinya, tipe lain null. */
    public function urlThumb(): ?string
    {
        if ($this->thumb_path) {
            return self::urlDari($this->thumb_path);
        }

        return $this->tipe === self::GAMBAR ? $this->url() : null;
    }

    public function labelTipe(): string
    {
        return self::TIPE[$this->tipe] ?? 'Dokumen';
    }

    public function ekstensi(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }
}
