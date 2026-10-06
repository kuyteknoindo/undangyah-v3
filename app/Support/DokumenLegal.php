<?php

namespace App\Support;

use App\Models\Pengaturan;

/**
 * Isi Syarat & Ketentuan dan Kebijakan Privasi. Bawaan = teks contoh dari desain.
 * Pemilik mengganti lewat Pengaturan: `legal.syarat` / `legal.privasi` = [{judul, isi}, ...],
 * `legal.diperbarui` = "1 Oktober 2026", `legal.contoh` = false setelah dokumen resmi dipasang (menyembunyikan catatan).
 */
class DokumenLegal
{
    public const JUDUL = ['syarat' => 'Syarat & Ketentuan', 'privasi' => 'Kebijakan Privasi'];

    public const BAWAAN = [
        'syarat' => [
            ['judul' => 'Penerimaan syarat', 'isi' => 'Dengan membuat akun dan memakai undangyah.com, kamu menyetujui syarat ini. Jika tidak setuju, mohon berhenti memakai layanan.'],
            ['judul' => 'Akun dan keamanan', 'isi' => 'Kamu bertanggung jawab atas keamanan akun dan kata sandi. Segera hubungi kami jika ada akses yang mencurigakan.'],
            ['judul' => 'Konten undangan', 'isi' => 'Kamu memiliki konten yang kamu unggah, seperti foto dan teks. Pastikan kontennya milikmu atau kamu punya izin, dan tidak melanggar hukum.'],
            ['judul' => 'Paket dan pembayaran', 'isi' => 'Paket berlaku selama masa aktif yang tertera. Add-on dan perpanjangan ditagihkan sesuai harga saat pembelian. Pembayaran yang sudah berhasil tidak dapat dikembalikan, kecuali ditentukan lain oleh hukum.'],
            ['judul' => 'Pengiriman pesan ke tamu', 'isi' => 'Kamu bertanggung jawab atas daftar tamu dan isi pesan yang dikirim. Jangan memakai layanan untuk spam atau pesan yang tidak diminta.'],
            ['judul' => 'Perubahan dan penghentian', 'isi' => 'Kami dapat memperbarui syarat ini dan akan memberi tahu perubahan penting. Kami dapat menangguhkan akun yang melanggar syarat.'],
        ],
        'privasi' => [
            ['judul' => 'Data yang kami kumpulkan', 'isi' => 'Data akun (nama, email, nomor WhatsApp), data undangan (teks, foto, daftar tamu), serta data penggunaan seperti kunjungan dan respons RSVP.'],
            ['judul' => 'Cara kami memakai data', 'isi' => 'Untuk menyediakan layanan, mengirim notifikasi, memproses pembayaran, menjaga keamanan, dan meningkatkan produk.'],
            ['judul' => 'Data tamu', 'isi' => 'Nama dan nomor tamu hanya dipakai untuk mengirim undangan dan mencatat RSVP atas permintaanmu. Kami tidak menjual data tamu.'],
            ['judul' => 'Berbagi dengan pihak ketiga', 'isi' => 'Kami bekerja sama dengan penyedia pembayaran dan pengiriman pesan sebatas yang diperlukan untuk layanan.'],
            ['judul' => 'Penyimpanan dan keamanan', 'isi' => 'Data disimpan dengan perlindungan yang wajar. Undangan dan data tamu dihapus sesuai kebijakan retensi setelah masa aktif berakhir.'],
            ['judul' => 'Hak kamu', 'isi' => 'Kamu dapat mengakses, memperbarui, mengekspor, atau meminta penghapusan datamu lewat menu Profil atau dengan menghubungi kami.'],
        ],
    ];

    public const DIPERBARUI_BAWAAN = '1 Oktober 2026';

    public static function valid(string $dokumen): bool
    {
        return isset(self::BAWAAN[$dokumen]);
    }

    /** @return list<array{no:int,judul:string,isi:string,anchor:string}> */
    public static function bagian(string $dokumen): array
    {
        $kustom = Pengaturan::ambil('legal.'.$dokumen);
        $daftar = [];
        foreach (is_array($kustom) ? $kustom : [] as $b) {
            if (is_array($b) && is_string($b['judul'] ?? null) && is_string($b['isi'] ?? null) && trim($b['judul']) !== '') {
                $daftar[] = ['judul' => mb_substr(trim($b['judul']), 0, 120), 'isi' => mb_substr($b['isi'], 0, 5000)];
            }
        }
        $daftar = $daftar ?: self::BAWAAN[$dokumen];

        return array_map(
            fn ($b, $i) => ['no' => $i + 1, 'judul' => $b['judul'], 'isi' => $b['isi'], 'anchor' => $dokumen.'-'.($i + 1)],
            $daftar,
            array_keys($daftar),
        );
    }

    public static function diperbarui(): string
    {
        $t = trim(strip_tags((string) Pengaturan::ambil('legal.diperbarui', '')));

        return $t !== '' ? mb_substr($t, 0, 60) : self::DIPERBARUI_BAWAAN;
    }

    /** Catatan "teks contoh" tampil selama pemilik belum mematikannya. */
    public static function masihContoh(): bool
    {
        return (bool) Pengaturan::ambil('legal.contoh', true);
    }
}
