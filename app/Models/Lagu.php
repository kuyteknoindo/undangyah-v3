<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu lagu di katalog musik latar (dikelola admin, dipilih pengguna di editor lewat `kode`). */
class Lagu extends Model
{
    protected $table = 'lagu';

    protected $guarded = [];

    public const PUBLIK = 'publik';

    public const DRAF = 'draf';

    public const SUMBER = ['media' => 'Dari Media', 'tautan' => 'Link audio', 'youtube' => 'YouTube'];

    public const KATEGORI = ['Instrumental', 'Akustik', 'Pop', 'Orkestra'];

    protected function casts(): array
    {
        return ['durasi' => 'integer', 'urutan' => 'integer', 'bawaan' => 'boolean'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function scopePublik(Builder $q): void
    {
        $q->where('status', self::PUBLIK);
    }

    public function scopeUrut(Builder $q): void
    {
        $q->orderBy('urutan')->orderBy('id');
    }

    /** URL audio yang bisa diputar langsung (path situs atau http/https), atau null (YouTube / belum ada sumber). */
    public function urlAudio(): ?string
    {
        $url = match ($this->sumber) {
            'media' => $this->media?->tipe === Media::AUDIO ? $this->media->url() : null,
            'tautan' => $this->url,
            default => null,
        };

        return self::urlSah((string) $url) ? $url : null;
    }

    public function urlYoutube(): ?string
    {
        return $this->sumber === 'youtube' && self::urlYoutubeSah((string) $this->youtube) ? $this->youtube : null;
    }

    public function dapatDiputar(): bool
    {
        return $this->urlAudio() !== null || $this->urlYoutube() !== null;
    }

    /** "3:12" */
    public function durasiTeks(): string
    {
        return intdiv($this->durasi, 60).':'.str_pad((string) ($this->durasi % 60), 2, '0', STR_PAD_LEFT);
    }

    /** "m:ss" atau "mm:ss" => detik; null bila bukan format yang sah. */
    public static function durasiDariTeks(string $teks): ?int
    {
        if (! preg_match('/^(\d{1,3}):([0-5]\d)$/', trim($teks), $m)) {
            return null;
        }
        $detik = (int) $m[1] * 60 + (int) $m[2];

        return $detik > 0 && $detik <= 3 * 3600 ? $detik : null;
    }

    /** Path situs ("/storage/...") atau http/https berhost; tanpa spasi/karakter kontrol/tanda kutip. */
    public static function urlSah(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500 || preg_match('/[\s\x00-\x1F\x7F\\\\<>"\']/', $url)) {
            return false;
        }
        if (preg_match('#^/(?!/)[^?\#]*$#', $url)) {
            return true;
        }
        $p = parse_url($url);

        return is_array($p) && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && ! empty($p['host']) && str_contains($p['host'], '.');
    }

    public static function urlYoutubeSah(string $url): bool
    {
        if (! self::urlSah($url) || str_starts_with($url, '/')) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'www.youtu.be'], true);
    }
}
