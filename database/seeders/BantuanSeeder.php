<?php

namespace Database\Seeders;

use App\Models\BantuanArtikel;
use Illuminate\Database\Seeder;

/**
 * Artikel awal Pusat Bantuan dari desain. Idempoten: baris dengan `kode` yang sama tidak ditimpa.
 * Teks diselaraskan dengan kenyataan aplikasi (pembayaran transfer manual, OTP lewat email, tamu di RSVP & Tamu)
 * di tiga artikel yang di desain menyebut fitur yang belum ada.
 */
class BantuanSeeder extends Seeder
{
    public function run(): void
    {
        $artikel = [
            ['f1', 'undangan', 'Bagaimana cara membuat undangan baru?', 'Tekan Buat Undangan di menu samping, pilih jenis acara, isi detail, lalu pilih desain. Setelah itu lengkapi isinya lewat menu Edit Undangan.', 'buat-undangan', 'Buat undangan baru'],
            ['f2', 'undangan', 'Bagaimana cara mengganti template atau warna?', 'Buka menu Template Desain untuk memilih template, atau Edit Undangan lalu Tema untuk mengganti warna, huruf, dan foto sampul. Perubahan langsung tampil di undangan.', 'template', 'Buka Template Desain'],
            ['f3', 'undangan', 'Apa bedanya terbit dan draf?', 'Undangan yang terbit bisa dibuka tamu lewat link. Undangan draf disembunyikan sampai kamu menerbitkannya. Ubah statusnya lewat Edit Undangan.', 'edit-undangan', 'Buka Edit Undangan'],
            ['f4', 'tamu', 'Bagaimana cara menambah dan mengirim ke tamu?', 'Buka menu RSVP & Tamu untuk menambah tamu satu per satu atau mengimpor daftar. Lalu buka Bagikan Undangan dan tekan tombol WA di baris tamu untuk mengirim pesan yang sudah terisi namanya.', 'bagikan', 'Buka Bagikan Undangan'],
            ['f5', 'tamu', 'Bisakah tiap tamu memakai pesan yang berbeda?', 'Bisa. Pilih template dan salam khusus untuk tamu itu, atau atur beberapa tamu sekaligus lewat Template Pesan.', 'template-pesan', 'Buka Template Pesan'],
            ['f6', 'tamu', 'Bagaimana tamu mengonfirmasi kehadiran?', 'Tamu mengisi formulir RSVP di undangan. Hasilnya muncul di menu RSVP & Tamu, lengkap dengan jumlah orang yang hadir dan pengingat untuk yang belum konfirmasi.', 'rsvp', 'Buka RSVP & Tamu'],
            ['f7', 'akun', 'Saya lupa kata sandi. Apa yang harus dilakukan?', 'Di halaman Masuk, tekan Lupa kata sandi dan ikuti langkahnya. Kami akan mengirim kode ke email yang terdaftar.', null, null],
            ['f8', 'akun', 'Bagaimana cara mengubah nomor WhatsApp?', 'Buka Profil lalu Informasi Akun. Nomor baru tersimpan dengan status belum diverifikasi sampai verifikasi lewat WhatsApp tersedia.', 'akun.informasi', 'Buka Informasi Akun'],
            ['f9', 'bayar', 'Metode pembayaran apa saja yang tersedia?', 'Saat ini pembayaran lewat transfer bank manual. Pilih metode yang tersedia saat checkout di halaman Paket & Billing, transfer sesuai nominal, lalu unggah bukti pembayaran.', 'billing', 'Buka Paket & Billing'],
            ['f10', 'bayar', 'Bagaimana cara upgrade paket atau menambah add-on?', 'Buka Paket & Billing, pilih paket atau add-on yang diinginkan, lalu lanjut ke pembayaran. Fitur baru aktif setelah pembayaranmu diverifikasi admin.', 'billing', 'Buka Paket & Billing'],
        ];

        foreach ($artikel as $urutan => [$kode, $kategori, $judul, $isi, $rute, $label]) {
            BantuanArtikel::firstOrCreate(['kode' => $kode], [
                'kategori' => $kategori,
                'judul' => $judul,
                'isi' => $isi,
                'rute' => $rute,
                'rute_label' => $label,
                'urutan' => $urutan + 1,
                'publik' => true,
            ]);
        }
    }
}
