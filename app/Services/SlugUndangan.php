<?php

namespace App\Services;

use App\Models\Undangan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Alamat publik undangan: /{slug}. Hanya huruf kecil, angka, tanda hubung; 3-60 karakter.
 * Slug yang bentrok dengan rute aplikasi atau kata terlarang ditolak.
 */
class SlugUndangan
{
    public const MIN = 3;

    public const MAKS = 60;

    /** Kata yang tidak boleh jadi slug (rute aplikasi, berkas statis, kata sensitif). */
    public const TERLARANG = [
        'login', 'logout', 'masuk', 'keluar', 'daftar', 'register', 'signup', 'signin', 'lupa-sandi', 'reset', 'verifikasi-email',
        'beranda', 'dashboard', 'admin', 'administrator', 'api', 'livewire', 'up', 'offline', 'akun', 'profil', 'notifikasi',
        'bantuan', 'hubungi', 'billing', 'checkout', 'pembayaran', 'konfirmasi', 'konfirmasi-pembayaran', 'invoice', 'template', 'tema', 'bagikan', 'share', 'rsvp',
        'statistik', 'buku-tamu', 'hadiah', 'edit-undangan', 'buat-undangan', 'undangan', 'tamu', 'ucapan', 'syarat', 'privasi',
        'kebijakan', 'terms', 'privacy', 'storage', 'build', 'assets', 'static', 'public', 'vendor', 'media', 'musik', 'upload',
        'unduh', 'download', 'favicon', 'favicon-ico', 'robots', 'robots-txt', 'sitemap', 'sitemap-xml', 'manifest', 'sw', 'www',
        'app', 'dev', 'staging', 'test', 'testing', 'support', 'help', 'about', 'contact', 'home', 'index', 'null', 'undefined',
        'undangyah', 'cdn', 'mail', 'email', 'ftp', 'smtp', 'ns1', 'ns2', 'root', 'system', 'config', 'wp-admin', 'wp-login',
    ];

    /** Ubah teks bebas menjadi bentuk slug (tanpa memeriksa ketersediaan). */
    public function bersihkan(string $teks): string
    {
        $slug = Str::of($teks)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();

        return Str::limit($slug, self::MAKS, '');
    }

    /** @return string|null pesan galat, atau null bila slug valid (format + tidak terlarang). */
    public function galatFormat(string $slug): ?string
    {
        if (strlen($slug) < self::MIN) {
            return 'Alamat minimal '.self::MIN.' karakter.';
        }
        if (strlen($slug) > self::MAKS) {
            return 'Alamat maksimal '.self::MAKS.' karakter.';
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Alamat hanya boleh huruf kecil, angka, dan tanda hubung (tanpa spasi, tanpa tanda hubung di awal/akhir).';
        }
        if ($this->terlarang($slug)) {
            return 'Alamat ini tidak tersedia. Pilih alamat lain.';
        }

        return null;
    }

    public function terlarang(string $slug): bool
    {
        if (in_array($slug, self::TERLARANG, true)) {
            return true;
        }

        // Bentrok dengan segmen pertama rute terdaftar (mis. rute baru yang belum ada di daftar).
        foreach (Route::getRoutes()->getRoutes() as $rute) {
            $awal = explode('/', trim($rute->uri(), '/'))[0] ?? '';
            if ($awal !== '' && ! str_starts_with($awal, '{') && $awal === $slug) {
                return true;
            }
        }

        return false;
    }

    public function dipakai(string $slug, ?int $abaikanUndanganId = null): bool
    {
        return Undangan::where('slug', $slug)
            ->when($abaikanUndanganId, fn ($q) => $q->where('id', '!=', $abaikanUndanganId))
            ->exists();
    }

    /**
     * @param  ?int  $penggunaId  pemakai slug (bawaan: pengguna yang login); slug yang ditahan atas namanya sendiri boleh dipakai.
     * @return string|null pesan galat lengkap (format + ketersediaan + penahanan), atau null bila bisa dipakai.
     */
    public function galat(string $slug, ?int $abaikanUndanganId = null, ?int $penggunaId = null): ?string
    {
        return $this->galatFormat($slug)
            ?? ($this->dipakai($slug, $abaikanUndanganId) ? 'Alamat ini sudah dipakai undangan lain.' : null)
            ?? app(PenahananSlug::class)->galat($slug, $penggunaId ?? auth()->id());
    }

    /** Buat slug valid dan belum dipakai dari judul (menambah akhiran angka bila perlu). */
    public function buat(string $judul, ?int $abaikanUndanganId = null, ?int $penggunaId = null): string
    {
        $dasar = $this->bersihkan($judul);
        if (strlen($dasar) < self::MIN) {
            $dasar = 'undangan-'.($dasar !== '' ? $dasar : Str::lower(Str::random(4)));
        }
        $kandidat = $dasar;
        for ($i = 2; $this->galat($kandidat, $abaikanUndanganId, $penggunaId) !== null; $i++) {
            $kandidat = Str::limit($dasar, self::MAKS - strlen((string) $i) - 1, '').'-'.$i;
            if ($i > 500) {
                $kandidat = $dasar.'-'.Str::lower(Str::random(5));
                break;
            }
        }

        return $kandidat;
    }
}
