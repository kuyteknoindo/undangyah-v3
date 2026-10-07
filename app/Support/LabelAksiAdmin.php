<?php

namespace App\Support;

/** Label manusiawi (Bahasa Indonesia) untuk kode aksi di `log_aksi_admin`; kode yang belum terdaftar tetap terbaca. */
class LabelAksiAdmin
{
    private const LABEL = [
        'aktifkan' => 'Akun diaktifkan kembali',
        'tangguhkan' => 'Akun ditangguhkan',
        'hapus_akun' => 'Akun pengguna dihapus',
        'ubah_peran' => 'Peran pengguna diubah',
        'atur_paket' => 'Paket pengguna diatur manual',
        'verifikasi_manual' => 'Email diverifikasi manual',
        'kirim_ulang_verifikasi' => 'Verifikasi dikirim ulang',
        'impersonasi_mulai' => 'Masuk sebagai pengguna (mulai)',
        'impersonasi_selesai' => 'Masuk sebagai pengguna (selesai)',
        'konfirmasi_sandi' => 'Konfirmasi kata sandi berhasil',
        'konfirmasi_sandi_gagal' => 'Konfirmasi kata sandi gagal',
        'konfirmasi_sandi_terkunci' => 'Konfirmasi kata sandi terkunci',
        'pesanan_lunas' => 'Pesanan ditandai lunas',
        'pesanan_tolak' => 'Bukti pembayaran ditolak',
        'pesanan_batal' => 'Pesanan dibatalkan',
        'pesanan_kedaluwarsa' => 'Pesanan ditandai kedaluwarsa',
        'pesanan_tinjau' => 'Pesanan ditandai selesai ditinjau',
        'pesanan_refund' => 'Pesanan ditandai refund',
        'ekspor_pengguna' => 'Ekspor data pengguna (CSV)',
        'ekspor_pesanan' => 'Ekspor data pesanan (CSV)',
        'ekspor_invoice' => 'Ekspor data invoice (CSV)',
        'buat_backup' => 'Backup dibuat',
        'verifikasi_backup' => 'Backup diverifikasi',
        'uji_pulih_backup' => 'Uji pulih backup',
        'unduh_backup' => 'Backup diunduh',
        'hapus_backup' => 'Backup dihapus',
        'retensi_backup' => 'Backup lama dibersihkan',
        'atur_backup' => 'Pengaturan backup diubah',
        'atur_otp' => 'Pengaturan OTP diubah',
        'siaran_kirim' => 'Siaran notifikasi dikirim',
        'metode_bayar_buat' => 'Metode pembayaran ditambah',
        'ubah_rekening' => 'Rekening metode pembayaran diubah',
        'metode_bayar_aktif' => 'Metode pembayaran diaktif/nonaktifkan',
        'metode_bayar_hapus' => 'Metode pembayaran dihapus',
        'paket_buat' => 'Paket dibuat',
        'paket_ubah' => 'Paket diubah',
        'paket_hapus' => 'Paket dihapus',
        'addon_buat' => 'Add-on dibuat',
        'addon_ubah' => 'Add-on diubah',
        'addon_hapus' => 'Add-on dihapus',
        'voucher_buat' => 'Voucher dibuat',
        'voucher_ubah' => 'Voucher diubah',
        'voucher_aktif' => 'Voucher diaktif/nonaktifkan',
        'voucher_hapus' => 'Voucher dihapus',
        'tema_hapus_permanen' => 'Tema dihapus permanen',
        'media_hapus' => 'File media dihapus',
        'storage_atur' => 'Pengaturan storage diubah',
        'storage_bersih' => 'File tidak terpakai dibersihkan',
    ];

    public static function label(string $kode): string
    {
        return self::LABEL[$kode] ?? ucfirst(str_replace('_', ' ', $kode));
    }

    /**
     * Kode aksi yang labelnya memuat kata pencarian (huruf kecil, tanpa escape), agar mencari "lunas" atau "dihapus" menemukan barisnya.
     *
     * @return list<string>
     */
    public static function kodeCocok(string $kata): array
    {
        $kata = mb_strtolower(trim($kata));
        if ($kata === '') {
            return [];
        }

        return array_keys(array_filter(self::LABEL, fn (string $label) => str_contains(mb_strtolower($label), $kata)));
    }
}
