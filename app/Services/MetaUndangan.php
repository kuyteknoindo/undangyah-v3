<?php

namespace App\Services;

use App\Models\Undangan;

/** Meta SEO/OG halaman publik. Hanya membaca salinan terbitan, tidak pernah draf. */
class MetaUndangan
{
    public const DESKRIPSI_BAWAAN = 'Anda diundang. Buka undangan digital ini.';

    public const GAMBAR_BAWAAN = '/undangan/og-bawaan.png';

    public function __construct(private IsiUndangan $isian) {}

    /** @return array{judul:string,deskripsi:string,gambar:string,url:string,situs:string} */
    public function untuk(Undangan $u): array
    {
        $isi = (array) ($u->terbitan['isi'] ?? []);
        $judul = trim((string) ($isi['scJudul'] ?? '')) ?: trim((string) ($u->terbitan['judul'] ?? '')) ?: 'Undangan';

        $teks = collect(IsiUndangan::OPSI_DESKRIPSI)->firstWhere(0, (string) ($isi['scDesc'] ?? 'd1'))[1] ?? null;
        $deskripsi = $teks !== null ? $this->isian->isiLabel($teks, $isi) : self::DESKRIPSI_BAWAAN;

        return [
            'judul' => mb_substr($judul, 0, 120),
            'deskripsi' => mb_substr($deskripsi, 0, 300),
            'gambar' => $this->gambar($isi),
            'url' => route('undangan.publik', $u->slug),
            'situs' => (string) config('app.name'),
        ];
    }

    /** Foto share, lalu sampul, lalu gambar bawaan. Hanya http(s) atau jalur situs. */
    private function gambar(array $isi): string
    {
        foreach (['share', 'cover'] as $slot) {
            $f = $isi['_foto'][$slot] ?? null;
            $src = is_array($f) ? ($f['src'] ?? '') : $f;
            if (is_string($src) && ($aman = $this->urlGambar($src)) !== null) {
                return $aman;
            }
        }

        return url(self::GAMBAR_BAWAAN);
    }

    private function urlGambar(string $u): ?string
    {
        $u = trim($u);
        if ($u === '' || strlen($u) > 2000 || preg_match('/[\s\x00-\x1F\x7F\\\\<>"\']/', $u)) {
            return null;
        }
        if (preg_match('#^/(?!/)#', $u)) {
            return url($u);
        }
        $p = parse_url($u);

        return is_array($p) && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && ! empty($p['host']) && str_contains($p['host'], '.') ? $u : null;
    }
}
