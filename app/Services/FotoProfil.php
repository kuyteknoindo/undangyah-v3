<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan foto profil di disk publik dengan nama acak. Bila ekstensi GD ada, gambar dipotong persegi dari tengah,
 * diperkecil ke 256x256 dan disimpan ulang sebagai JPEG (sekaligus membuang metadata dan muatan tersembunyi).
 */
class FotoProfil
{
    public const UKURAN = 256;

    /** Batas piksel agar memori tidak habis saat gambar didekode (foto kamera 48 MP tidak diterima). */
    public const MAKS_PIKSEL = 24_000_000;

    private const DIR = 'avatar';

    /** @return string path relatif pada disk publik */
    public function simpan(UploadedFile $berkas): string
    {
        $isi = (string) file_get_contents($berkas->getRealPath());

        if (! extension_loaded('gd')) {
            $ext = in_array($berkas->extension(), ['jpg', 'jpeg', 'png', 'webp'], true) ? $berkas->extension() : 'jpg';
            $path = self::DIR.'/'.Str::random(40).'.'.$ext;
            Storage::disk('public')->put($path, $isi);

            return $path;
        }

        $info = @getimagesizefromstring($isi);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['foto' => 'Gambar tidak bisa dibaca. Coba file lain.']);
        }
        if ($info[0] * $info[1] > self::MAKS_PIKSEL) {
            throw ValidationException::withMessages(['foto' => 'Dimensi gambar terlalu besar. Perkecil dulu lalu coba lagi.']);
        }

        $sumber = @imagecreatefromstring($isi);
        if (! $sumber) {
            throw ValidationException::withMessages(['foto' => 'Gambar tidak bisa dibaca. Coba file lain.']);
        }

        $sisi = min($info[0], $info[1]);
        $hasil = imagecreatetruecolor(self::UKURAN, self::UKURAN);
        imagefill($hasil, 0, 0, imagecolorallocate($hasil, 255, 255, 255));
        imagecopyresampled($hasil, $sumber, 0, 0, intdiv($info[0] - $sisi, 2), intdiv($info[1] - $sisi, 2), self::UKURAN, self::UKURAN, $sisi, $sisi);
        unset($sumber);

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $hasil = $this->putarSesuaiExif($hasil, $isi);
        }

        ob_start();
        imagejpeg($hasil, null, 85);
        $jpeg = (string) ob_get_clean();

        $path = self::DIR.'/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }

    public function hapus(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR.'/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function putarSesuaiExif(\GdImage $gambar, string $isi): \GdImage
    {
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($isi));
        $sudut = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $sudut ? (imagerotate($gambar, $sudut, 0) ?: $gambar) : $gambar;
    }
}
