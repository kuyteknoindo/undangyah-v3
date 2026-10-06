<?php

namespace Tests\Feature\Concerns;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/** Berkas uji dengan isi sungguhan (deteksi jenis memeriksa isi, bukan nama atau mime kiriman). */
trait BerkasUji
{
    protected function gambar(string $nama = 'foto.jpg', int $w = 800, int $h = 600): UploadedFile
    {
        return UploadedFile::fake()->image($nama, $w, $h);
    }

    protected function mp3(string $nama = 'lagu.mp3'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, str_repeat("\xFF\xFB\x90\x64".str_repeat("\0", 413), 6));
    }

    protected function wav(string $nama = 'bunyi.wav'): UploadedFile
    {
        $isi = 'RIFF'.pack('V', 44).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8).'data'.pack('V', 8).str_repeat("\x80", 8);

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    protected function ogg(string $nama = 'lagu.ogg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "OggS\x00\x02".str_repeat("\0", 20)."\x01\x1e\x01vorbis".str_repeat("\0", 60));
    }

    protected function m4a(string $nama = 'lagu.m4a'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "\x00\x00\x00\x20ftypM4A \x00\x00\x00\x00M4A mp42isom".str_repeat("\0", 64));
    }

    protected function mp4(string $nama = 'klip.mp4'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\0", 64));
    }

    protected function pdf(string $nama = 'dok.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    protected function teks(string $nama = 'catatan.txt', string $isi = "halo dunia\nbaris dua\n"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    /** .docx minimal (zip dengan penanda Word); $makro menambahkan vbaProject.bin. */
    protected function docx(string $nama = 'surat.docx', bool $makro = false): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');
        if ($makro) {
            $zip->addFromString('word/vbaProject.bin', 'x');
        }
        $zip->close();
        $isi = (string) file_get_contents($tmp);
        @unlink($tmp);

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    protected function zipBiasa(string $nama = 'arsip.zip'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zp');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('a.txt', 'a');
        $zip->close();
        $isi = (string) file_get_contents($tmp);
        @unlink($tmp);

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    /** JPEG sungguhan yang disisipi segmen EXIF berisi penanda rahasia (stand-in untuk GPS). */
    protected function jpegBerExif(string $nama = 'ponsel.jpg'): UploadedFile
    {
        $g = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($g);
        $dasar = (string) ob_get_clean();
        $exif = "Exif\0\0RAHASIA-GPS-LOKASI";
        $segmen = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return UploadedFile::fake()->createWithContent($nama, substr($dasar, 0, 2).$segmen.substr($dasar, 2));
    }
}
