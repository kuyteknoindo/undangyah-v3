<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Paket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Satu-satunya pintu masuk unggahan media (halaman Media admin, pemilih foto di editor, dsb.).
 *
 * API untuk fitur lain:
 *   $media = app(PenyimpanMedia::class)->simpan($berkas, $user, ['tipe' => ['gambar'], 'folder' => 'Foto']);  // throw MediaDitolak
 *   $media->url() / $media->urlThumb()      // path situs "/storage/media/...", aman untuk `_foto`, `_latarTetap`, musik
 *   $penyimpan->hapus($media)               // menolak bila `dipakai` atau dirujuk undangan (paksa: true untuk admin)
 *   $penyimpan->sisaBytes($user), kuotaBytes($user), terpakaiBytes($user), batasFotoGaleri($user)
 *   app(ReferensiMedia::class)->sinkronPemilik($user->id)   // setel `dipakai` sesuai isi undangan, panggil setelah editor menyimpan
 *
 * Keamanan: jenis ditentukan dari ISI berkas (getimagesize/finfo/penanda berkas), bukan ekstensi atau mime kiriman klien; ekstensi
 * simpanan dipilih dari hasil deteksi dan nama berkas selalu acak. SVG, HTML, XML, dan skrip ditolak. JPEG/PNG disimpan ulang lewat
 * GD (membuang EXIF termasuk GPS dan muatan tersembunyi); thumbnail dibuat dengan GD dan gagalnya GD tidak menggagalkan unggahan.
 */
class PenyimpanMedia
{
    /** Batas piksel agar memori tidak habis saat gambar didekode (sama dengan FotoProfil). */
    public const MAKS_PIKSEL = 24_000_000;

    public const SISI_THUMB = 480;

    /** Unggahan per pelaku per 10 menit (termasuk yang ditolak). */
    public const BATAS_UNGGAH = 60;

    public const EKSTENSI = [
        Media::GAMBAR => ['jpg', 'png', 'webp', 'gif'],
        Media::AUDIO => ['mp3', 'm4a', 'ogg', 'wav'],
        Media::VIDEO => ['mp4', 'webm'],
        Media::DOKUMEN => ['pdf', 'docx', 'xlsx', 'pptx', 'txt', 'csv'],
    ];

    private const MIME_AUDIO = [
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/mpeg3'],
        'ogg' => ['audio/ogg', 'application/ogg', 'audio/vorbis', 'audio/opus', 'audio/x-vorbis+ogg', 'audio/x-opus+ogg'],
        'wav' => ['audio/x-wav', 'audio/wav', 'audio/wave', 'audio/vnd.wave'],
    ];

    private const MIME_BERBAHAYA = ['image/svg+xml', 'text/html', 'application/xhtml+xml', 'text/xml', 'application/xml', 'text/x-php', 'application/x-httpd-php', 'text/javascript', 'application/javascript', 'application/x-sh'];

    // ------------------------------------------------------------------ unggah

    /**
     * @param  array{tipe?:list<string>,folder?:string,alt?:string,pelaku?:User}  $opsi  tipe = batasi jenis yang diterima (mis. ['gambar'])
     *
     * @throws MediaDitolak
     */
    public function simpan(UploadedFile $berkas, ?User $pemilik, array $opsi = []): Media
    {
        $this->batasiLaju($opsi['pelaku'] ?? $pemilik ?? auth()->user());

        if (! $berkas->isValid() || ! is_file((string) $berkas->getRealPath())) {
            throw new MediaDitolak('File gagal diunggah. Coba lagi.');
        }
        $nama = $this->bersihkanNama((string) $berkas->getClientOriginalName());
        $konfig = KonfigStorage::ambil();
        $ukuran = (int) $berkas->getSize();
        if ($ukuran <= 0) {
            throw new MediaDitolak("{$nama}: file kosong.");
        }
        if ($ukuran > $konfig['maks_byte']) {
            throw new MediaDitolak("{$nama} (".self::teksUkuran($ukuran).'): melebihi batas '.self::teksUkuran($konfig['maks_byte']).' per file.');
        }

        $d = $this->deteksi($berkas, $nama);

        if (isset($opsi['tipe']) && ! in_array($d['tipe'], $opsi['tipe'], true)) {
            throw new MediaDitolak("{$nama}: hanya file ".implode(' atau ', array_map(fn ($t) => mb_strtolower(Media::TIPE[$t] ?? $t), $opsi['tipe'])).' yang diterima di sini.');
        }
        if (! ($konfig['tipe'][$d['tipe']] ?? false)) {
            throw new MediaDitolak("{$nama}: unggahan ".mb_strtolower(Media::TIPE[$d['tipe']]).' sedang dinonaktifkan di pengaturan Storage.');
        }
        if ($pemilik !== null && $ukuran > $this->sisaBytes($pemilik)) {
            throw new MediaDitolak("{$nama}: kuota penyimpanan ".self::teksUkuran($konfig['kuota_byte']).' akan terlampaui (terpakai '.self::teksUkuran($this->terpakaiBytes($pemilik)).'). Hapus file lain terlebih dahulu.');
        }

        $isi = null; // null = simpan berkas asli apa adanya
        $thumb = null;
        $lebar = $d['lebar'];
        $tinggi = $d['tinggi'];
        if ($d['tipe'] === Media::GAMBAR) {
            $h = $this->prosesGambar($berkas->getRealPath(), $d, $nama);
            [$isi, $thumb, $lebar, $tinggi] = [$h['isi'], $h['thumb'], $h['lebar'], $h['tinggi']];
        }

        $dir = ($pemilik ? 'u'.$pemilik->id : 'sistem').'/'.now()->format('Y/m');
        $acak = bin2hex(random_bytes(16));
        $path = "{$dir}/{$acak}.{$d['ext']}";
        $thumbPath = $thumb !== null ? "{$dir}/{$acak}_t.{$thumb['ext']}" : null;
        $disk = Storage::disk(Media::DISK);

        $tulis = $isi !== null ? $disk->put($path, $isi) : $this->tulisStream($berkas->getRealPath(), $path);
        if (! $tulis) {
            throw new MediaDitolak("{$nama}: gagal menyimpan file di server.");
        }
        if ($thumbPath !== null && ! $disk->put($thumbPath, $thumb['isi'])) {
            $thumbPath = null;
        }

        try {
            return Media::create([
                'user_id' => $pemilik?->id,
                'nama' => $nama,
                'path' => $path,
                'thumb_path' => $thumbPath,
                'tipe' => $d['tipe'],
                'mime' => $d['mime'],
                'ukuran' => $isi !== null ? strlen($isi) : $ukuran,
                'lebar' => $lebar,
                'tinggi' => $tinggi,
                'folder' => $this->folderSah($opsi['folder'] ?? null) ?? self::folderBawaan($d['tipe']),
                'alt' => isset($opsi['alt']) ? mb_substr(trim((string) $opsi['alt']), 0, 300) : null,
                'dipakai' => false,
            ]);
        } catch (\Throwable $e) {
            $disk->delete(array_filter([$path, $thumbPath]));
            throw $e;
        }
    }

    // ------------------------------------------------------------------ hapus

    /**
     * Hapus media beserta berkasnya. Ditolak bila ditandai dipakai atau dirujuk undangan/tema/lagu, kecuali $paksa.
     *
     * @throws MediaDitolak
     */
    public function hapus(Media $media, bool $paksa = false): void
    {
        if (! $paksa) {
            if ($media->dipakai) {
                throw new MediaDitolak("{$media->nama} ditandai sedang dipakai. Lepaskan dulu dari undangan atau hapus dengan konfirmasi.");
            }
            $rujukan = app(ReferensiMedia::class)->cari([$media]);
            if ($rujukan !== []) {
                throw new MediaDitolak("{$media->nama} masih dirujuk (".app(ReferensiMedia::class)->ringkas($rujukan[$media->id]).').');
            }
        }
        $media->delete();
    }

    /** Hapus semua media milik pengguna (dipanggil saat akun dihapus; cascade basis data tidak menyentuh berkas). */
    public function hapusMilik(User $user): int
    {
        $n = 0;
        Media::where('user_id', $user->id)->orderBy('id')->each(function (Media $m) use (&$n) {
            $m->delete();
            $n++;
        });

        return $n;
    }

    public function tandaiDipakai(Media $media, bool $dipakai = true): void
    {
        if ($media->dipakai !== $dipakai) {
            $media->forceFill(['dipakai' => $dipakai])->save();
        }
    }

    // ------------------------------------------------------------------ kuota

    /** Kuota byte pengguna; null untuk aset sistem (tanpa kuota). */
    public function kuotaBytes(?User $user): ?int
    {
        return $user === null ? null : KonfigStorage::ambil()['kuota_byte'];
    }

    public function terpakaiBytes(?User $user): int
    {
        return (int) Media::milik($user)->sum('ukuran');
    }

    /** Sisa kuota; PHP_INT_MAX untuk aset sistem. */
    public function sisaBytes(?User $user): int
    {
        $kuota = $this->kuotaBytes($user);

        return $kuota === null ? PHP_INT_MAX : max(0, $kuota - $this->terpakaiBytes($user));
    }

    /** Maksimal foto galeri per undangan menurut paket aktif pengguna (`paket.kuota_foto`); dipakai pemilih foto/galeri di editor. */
    public function batasFotoGaleri(User $user): int
    {
        $paket = Paket::where('kode', $user->paketAktif())->first() ?? Paket::where('kode', 'gratis')->first();

        return $paket ? (int) $paket->kuota_foto : 3;
    }

    // ------------------------------------------------------------------ utilitas

    public static function folderBawaan(string $tipe): string
    {
        return match ($tipe) {
            Media::AUDIO => 'Musik',
            Media::VIDEO => 'Video',
            Media::DOKUMEN => 'Dokumen',
            default => 'Umum',
        };
    }

    /** "1,5 MB" */
    public static function teksUkuran(int|float $byte): string
    {
        $byte = max(0, (float) $byte);
        if ($byte < 1024) {
            return (int) $byte.' B';
        }
        foreach (['KB', 'MB', 'GB', 'TB'] as $i => $satuan) {
            $nilai = $byte / (1024 ** ($i + 1));
            if ($nilai < 1024 || $satuan === 'TB') {
                return rtrim(rtrim(number_format($nilai, $nilai < 10 ? 2 : ($nilai < 100 ? 1 : 0), ',', '.'), '0'), ',').' '.$satuan;
            }
        }

        return $byte.' B';
    }

    public function folderSah(?string $folder): ?string
    {
        return in_array($folder, Media::FOLDER, true) ? $folder : null;
    }

    /** Nama tampilan aman: tanpa jalur/karakter kontrol, maksimal 120 karakter. */
    public function bersihkanNama(string $nama): string
    {
        $nama = (string) preg_replace('/[\x00-\x1F\x7F\\\\\/:*?"<>|]+/u', ' ', $nama);
        $nama = trim((string) preg_replace('/\s+/u', ' ', $nama), " .\t");
        if ($nama === '' || ! mb_check_encoding($nama, 'UTF-8')) {
            return 'file';
        }

        return mb_substr($nama, 0, 120);
    }

    private function batasiLaju(?User $pelaku): void
    {
        $kunci = 'media-unggah:'.($pelaku?->id ?? request()?->ip() ?? 'x');
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_UNGGAH)) {
            throw new MediaDitolak('Terlalu banyak unggahan dalam waktu singkat. Coba lagi beberapa menit lagi.');
        }
        RateLimiter::hit($kunci, 600);
    }

    private function tulisStream(string $sumber, string $path): bool
    {
        $s = fopen($sumber, 'rb');
        if ($s === false) {
            return false;
        }
        try {
            return (bool) Storage::disk(Media::DISK)->put($path, $s);
        } finally {
            fclose($s);
        }
    }

    // ------------------------------------------------------------------ deteksi jenis dari isi

    /**
     * @return array{tipe:string,ext:string,mime:string,lebar:?int,tinggi:?int}
     *
     * @throws MediaDitolak
     */
    private function deteksi(UploadedFile $berkas, string $nama): array
    {
        $path = (string) $berkas->getRealPath();
        $kepala = (string) file_get_contents($path, false, null, 0, 8192);
        $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($path));
        $extKlien = strtolower(pathinfo((string) $berkas->getClientOriginalName(), PATHINFO_EXTENSION));

        // Gambar: dibuktikan getimagesize (membaca header sungguhan).
        $info = @getimagesize($path);
        if (is_array($info) && isset($info[2])) {
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null;
            if ($ext === null) {
                throw new MediaDitolak("{$nama}: format gambar tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.");
            }
            if (($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
                throw new MediaDitolak("{$nama}: gambar tidak bisa dibaca.");
            }

            return ['tipe' => Media::GAMBAR, 'ext' => $ext, 'mime' => image_type_to_mime_type($info[2]), 'lebar' => (int) $info[0], 'tinggi' => (int) $info[1]];
        }
        if (in_array($mime, self::MIME_BERBAHAYA, true) || $this->miripMarkup($kepala)) {
            throw new MediaDitolak("{$nama}: file SVG, HTML, XML, atau skrip tidak diterima. Gunakan JPG, PNG, WEBP, atau GIF untuk gambar.");
        }

        $merek = substr($kepala, 4, 4) === 'ftyp' ? strtoupper(trim(substr($kepala, 8, 4))) : null;

        // Audio
        foreach (self::MIME_AUDIO as $ext => $daftar) {
            if (in_array($mime, $daftar, true)) {
                if ($ext === 'wav' && ! (str_starts_with($kepala, 'RIFF') && substr($kepala, 8, 4) === 'WAVE')) {
                    break;
                }
                if ($ext === 'ogg' && ! str_starts_with($kepala, 'OggS')) {
                    break;
                }

                return ['tipe' => Media::AUDIO, 'ext' => $ext, 'mime' => $daftar[0], 'lebar' => null, 'tinggi' => null];
            }
        }
        if ($merek !== null && (in_array($merek, ['M4A', 'M4B', 'M4P'], true) || ($extKlien === 'm4a' && ($mime === 'video/mp4' || str_starts_with($mime, 'audio/'))))) {
            return ['tipe' => Media::AUDIO, 'ext' => 'm4a', 'mime' => 'audio/mp4', 'lebar' => null, 'tinggi' => null];
        }

        // Video
        if ($merek !== null && $mime === 'video/mp4') {
            return ['tipe' => Media::VIDEO, 'ext' => 'mp4', 'mime' => 'video/mp4', 'lebar' => null, 'tinggi' => null];
        }
        if ($mime === 'video/webm' && str_starts_with($kepala, "\x1A\x45\xDF\xA3")) {
            return ['tipe' => Media::VIDEO, 'ext' => 'webm', 'mime' => 'video/webm', 'lebar' => null, 'tinggi' => null];
        }

        // Dokumen
        if ($mime === 'application/pdf' && str_contains(substr($kepala, 0, 1024), '%PDF-')) {
            return ['tipe' => Media::DOKUMEN, 'ext' => 'pdf', 'mime' => 'application/pdf', 'lebar' => null, 'tinggi' => null];
        }
        if (str_starts_with($kepala, "PK\x03\x04")) {
            $office = $this->deteksiOffice($path);
            if ($office !== null) {
                return ['tipe' => Media::DOKUMEN, 'ext' => $office[0], 'mime' => $office[1], 'lebar' => null, 'tinggi' => null];
            }
            throw new MediaDitolak("{$nama}: arsip ZIP tidak diterima. Dokumen Office hanya .docx, .xlsx, atau .pptx tanpa makro.");
        }
        if (in_array($mime, ['text/plain', 'text/csv', 'application/csv'], true) && $this->teksBersih($path, $kepala)) {
            $csv = $extKlien === 'csv';

            return ['tipe' => Media::DOKUMEN, 'ext' => $csv ? 'csv' : 'txt', 'mime' => $csv ? 'text/csv' : 'text/plain', 'lebar' => null, 'tinggi' => null];
        }

        throw new MediaDitolak("{$nama}: jenis file tidak didukung. Gunakan gambar (JPG, PNG, WEBP, GIF), audio (MP3, M4A, OGG, WAV), video (MP4, WEBM), atau dokumen (PDF, DOCX, XLSX, PPTX, TXT, CSV).");
    }

    /** Awal berkas berupa HTML/XML/PHP/skrip walau mime terdeteksi lain. */
    private function miripMarkup(string $kepala): bool
    {
        $awal = strtolower(ltrim(substr($kepala, 0, 512), "\xEF\xBB\xBF \t\r\n"));

        return (bool) preg_match('/^(<\?(php|xml)|<svg|<html|<!doctype|<script|<head|<body|<\?=)/', $awal);
    }

    private function teksBersih(string $path, string $kepala): bool
    {
        if (str_contains($kepala, "\0")) {
            return false;
        }
        $isi = (string) file_get_contents($path, false, null, 0, 65536);
        // Potongan di tengah karakter multibita di ujung bacaan tidak dianggap rusak.
        for ($i = 0; $i < 4 && $isi !== '' && ! mb_check_encoding($isi, 'UTF-8'); $i++) {
            $isi = substr($isi, 0, -1);
        }

        return mb_check_encoding($isi, 'UTF-8') && ! $this->miripMarkup($isi);
    }

    /** @return array{0:string,1:string}|null [ekstensi, mime] untuk .docx/.xlsx/.pptx tanpa makro */
    private function deteksiOffice(string $path): ?array
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > 3000 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName('vbaProject.bin', ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR) !== false) {
                return null;
            }
            foreach ([
                ['docx', 'word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                ['xlsx', 'xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ['pptx', 'ppt/presentation.xml', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ] as [$ext, $penanda, $mime]) {
                if ($zip->locateName($penanda) !== false) {
                    return [$ext, $mime];
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    // ------------------------------------------------------------------ gambar (GD)

    /**
     * @param  array{ext:string,lebar:?int,tinggi:?int}  $d
     * @return array{isi:?string,thumb:?array{isi:string,ext:string},lebar:?int,tinggi:?int} isi null = pakai berkas asli
     *
     * @throws MediaDitolak
     */
    private function prosesGambar(string $path, array $d, string $nama): array
    {
        $lebar = $d['lebar'];
        $tinggi = $d['tinggi'];
        $hasil = ['isi' => null, 'thumb' => null, 'lebar' => $lebar, 'tinggi' => $tinggi];

        if ($lebar * $tinggi > self::MAKS_PIKSEL) {
            throw new MediaDitolak("{$nama}: dimensi gambar terlalu besar ({$lebar}x{$tinggi}). Perkecil dulu lalu coba lagi.");
        }
        $wajibSimpanUlang = in_array($d['ext'], ['jpg', 'png'], true);
        if (! extension_loaded('gd')) {
            return $hasil;
        }
        if (! $this->memoriCukup($lebar, $tinggi)) {
            if ($wajibSimpanUlang) {
                throw new MediaDitolak("{$nama}: dimensi gambar terlalu besar untuk diproses. Perkecil dulu lalu coba lagi.");
            }

            return $hasil;
        }

        $gambar = $this->dekode($path, $d['ext']);
        if ($gambar === null) {
            if ($wajibSimpanUlang) {
                throw new MediaDitolak("{$nama}: gambar rusak atau tidak bisa dibaca.");
            }

            return $hasil; // WEBP animasi/GIF: simpan asli tanpa thumbnail
        }

        try {
            if ($d['ext'] === 'jpg') {
                $gambar = $this->putarSesuaiExif($gambar, $path);
                $lebar = imagesx($gambar);
                $tinggi = imagesy($gambar);
            }
            if ($wajibSimpanUlang) {
                $hasil['isi'] = $this->enkode($gambar, $d['ext']);
                if ($hasil['isi'] === null) {
                    throw new MediaDitolak("{$nama}: gambar tidak bisa diproses.");
                }
            }
            $hasil['lebar'] = $lebar;
            $hasil['tinggi'] = $tinggi;
            $hasil['thumb'] = $this->buatThumb($gambar, $lebar, $tinggi);
        } finally {
            unset($gambar);
        }

        return $hasil;
    }

    private function memoriCukup(int $lebar, int $tinggi): bool
    {
        $batas = ini_get('memory_limit');
        if ($batas === false || $batas === '-1') {
            return true;
        }
        $byte = (int) $batas;
        $satuan = strtolower(substr(trim($batas), -1));
        $byte *= match ($satuan) {
            'g' => 1073741824,
            'm' => 1048576,
            'k' => 1024,
            default => 1,
        };

        // Satu salinan truecolor 4 byte/piksel; satu lagi untuk putar/skala.
        return memory_get_usage(true) + $lebar * $tinggi * 4 * 2.2 < $byte;
    }

    private function dekode(string $path, string $ext): ?\GdImage
    {
        $g = match ($ext) {
            'jpg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        return $g instanceof \GdImage ? $g : null;
    }

    private function enkode(\GdImage $g, string $ext): ?string
    {
        ob_start();
        if ($ext === 'jpg') {
            $ok = imagejpeg($g, null, 88);
        } else {
            imagealphablending($g, false);
            imagesavealpha($g, true);
            $ok = imagepng($g, null, 6);
        }
        $isi = (string) ob_get_clean();

        return $ok && $isi !== '' ? $isi : null;
    }

    /** Terapkan orientasi EXIF (foto ponsel) sebelum metadata dibuang saat disimpan ulang. */
    private function putarSesuaiExif(\GdImage $g, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $g;
        }
        $exif = @exif_read_data($path);
        $sudut = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($sudut === 0) {
            return $g;
        }
        $putar = @imagerotate($g, $sudut, 0);

        return $putar instanceof \GdImage ? $putar : $g;
    }

    /** @return array{isi:string,ext:string}|null */
    private function buatThumb(\GdImage $g, int $lebar, int $tinggi): ?array
    {
        try {
            $skala = min(1, self::SISI_THUMB / max($lebar, $tinggi));
            $w = max(1, (int) round($lebar * $skala));
            $h = max(1, (int) round($tinggi * $skala));
            $kecil = imagecreatetruecolor($w, $h);
            imagealphablending($kecil, false);
            imagesavealpha($kecil, true);
            imagefill($kecil, 0, 0, imagecolorallocatealpha($kecil, 255, 255, 255, 127));
            imagecopyresampled($kecil, $g, 0, 0, 0, 0, $w, $h, $lebar, $tinggi);

            ob_start();
            if (function_exists('imagewebp')) {
                $ok = imagewebp($kecil, null, 80);
                $ext = 'webp';
            } else {
                $latar = imagecreatetruecolor($w, $h);
                imagefill($latar, 0, 0, imagecolorallocate($latar, 255, 255, 255));
                imagecopy($latar, $kecil, 0, 0, 0, 0, $w, $h);
                $ok = imagejpeg($latar, null, 80);
                $ext = 'jpg';
            }
            $isi = (string) ob_get_clean();

            return $ok && $isi !== '' ? ['isi' => $isi, 'ext' => $ext] : null;
        } catch (\Throwable) {
            if (ob_get_level() > 0) {
                @ob_end_clean();
            }

            return null;
        }
    }
}
