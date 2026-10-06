<?php

namespace App\Models;

use App\Services\PenahananSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Undangan extends Model
{
    use HasFactory;

    protected $table = 'undangan';

    protected $guarded = [];

    public const DRAF = 'draf';

    public const TERBIT = 'terbit';

    /**
     * Semua perubahan slug lewat sini (editor, Link Kustom, terbit ulang, wizard) agar penahanan 90 hari tidak terlewat:
     * slug lama yang pernah terbit ditahan, slug baru ditolak bila ditahan orang lain.
     */
    protected static function booted(): void
    {
        static::saving(function (Undangan $u) {
            if ($u->exists && $u->isDirty('status') && $u->getOriginal('status') === self::TERBIT && $u->status !== self::TERBIT) {
                // Draf yang pernah terbit: slug-nya pernah publik, jadi tetap ditahan bila diganti kemudian.
                $u->pengaturan = [...(array) $u->pengaturan, 'pernah_terbit' => true];
            }
            if ($u->isDirty('slug') && filled($u->slug)) {
                app(PenahananSlug::class)->pastikan($u->slug, (int) $u->user_id);
            }
        });

        static::saved(function (Undangan $u) {
            if (! $u->isDirty('slug') || blank($u->slug)) {
                return;
            }
            $penahanan = app(PenahananSlug::class);
            // Dicek ulang di dalam transaksi yang sama dengan penulisan (lihat save()): dua permintaan serentak tidak lolos.
            $penahanan->pastikan($u->slug, (int) $u->user_id);

            $lama = $u->getOriginal('slug');
            $pernahPublik = $u->getOriginal('status') === self::TERBIT || ($u->pengaturan['pernah_terbit'] ?? false);
            if (filled($lama) && $lama !== $u->slug && $pernahPublik) {
                $penahanan->tahan($lama, (int) $u->user_id, $u->id, SlugDitahan::GANTI);
            }
            $penahanan->lepas($u->slug, (int) $u->user_id);
        });

        static::deleting(function (Undangan $u) {
            app(PenahananSlug::class)->tahanKarenaHapus($u);
        });
    }

    public function save(array $options = []): bool
    {
        if (! $this->isDirty('slug')) {
            return parent::save($options);
        }

        return DB::transaction(fn () => parent::save($options));
    }

    protected function casts(): array
    {
        return [
            'isi' => 'array',
            'bagian' => 'array',
            'pengaturan' => 'array',
            'terbitan' => 'array',
            'langkah' => 'array',
            'diterbitkan_at' => 'datetime',
            'draf_disimpan_at' => 'datetime',
            'tanggal_acara' => 'date',
            'ada_perubahan' => 'boolean',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class, 'tema_id')->withTrashed();
    }

    public function tamu(): HasMany
    {
        return $this->hasMany(Tamu::class, 'undangan_id');
    }

    public function rsvp(): HasMany
    {
        return $this->hasMany(Rsvp::class, 'undangan_id');
    }

    public function ucapan(): HasMany
    {
        return $this->hasMany(Ucapan::class, 'undangan_id');
    }

    public function tayangan(): HasMany
    {
        return $this->hasMany(Tayangan::class, 'undangan_id');
    }

    public function sudahTerbit(): bool
    {
        return $this->status === self::TERBIT;
    }
}
