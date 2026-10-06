<?php

namespace Database\Seeders;

use App\Models\AddOn;
use App\Models\MetodePembayaran;
use App\Models\Paket;
use App\Models\Pengaturan;
use App\Models\Voucher;
use App\Services\SinkronKuotaPaket;
use Illuminate\Database\Seeder;

/**
 * Katalog awal dari desain (Paket & Billing, Panel Admin). Idempoten dan tidak menimpa suntingan admin:
 * baris yang sudah ada dibiarkan. Rekening/nomor di metode pembayaran adalah PLACEHOLDER (data.placeholder = true):
 * admin wajib menggantinya di Metode Pembayaran sebelum dipakai pelanggan nyata.
 */
class PaketSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['kode' => 'gratis', 'sku' => 'P-FREE', 'nama' => 'Gratis', 'tagline' => 'Untuk mencoba undangyah.com', 'harga' => 0, 'durasi_bulan' => 0, 'kuota_undangan' => 1, 'kuota_foto' => 3, 'populer' => false, 'status' => 'aktif', 'urutan' => 1,
                'fitur' => ['1 undangan aktif', 'Template gratis', 'Galeri hingga 3 foto', 'RSVP dan ucapan tamu', 'Ada logo undangyah.com']],
            ['kode' => 'premium', 'sku' => 'P-PREM', 'nama' => 'Premium', 'tagline' => 'Paling banyak dipilih untuk pernikahan', 'harga' => 249000, 'durasi_bulan' => 12, 'kuota_undangan' => 3, 'kuota_foto' => 20, 'populer' => true, 'status' => 'aktif', 'urutan' => 2,
                'fitur' => ['Hingga 3 undangan aktif', 'Semua template Gratis dan Premium', 'RSVP, ucapan, dan buku tamu', 'Galeri hingga 20 foto', 'Musik latar', 'Masa aktif 12 bulan', 'Tanpa logo undangyah.com']],
            ['kode' => 'deluxe', 'sku' => 'P-DLX', 'nama' => 'Deluxe', 'tagline' => 'Lengkap untuk acara besar', 'harga' => 499000, 'durasi_bulan' => 24, 'kuota_undangan' => 10, 'kuota_foto' => 20, 'populer' => false, 'status' => 'draf', 'urutan' => 3,
                'fitur' => ['Hingga 10 undangan aktif', 'Semua template, termasuk Deluxe', 'Live streaming dan kado digital', 'Domain kustom', 'Blast WhatsApp 500 pesan', 'Masa aktif 24 bulan', 'Dukungan prioritas']],
        ] as $baris) {
            Paket::firstOrCreate(['kode' => $baris['kode']], $baris);
        }

        foreach ([
            ['kode' => 'kuota-undangan', 'nama' => 'Tambah kuota undangan', 'deskripsi' => '+1 undangan aktif untuk akunmu', 'harga' => 25000, 'multi' => true, 'efek' => ['kuota_undangan' => 1]],
            ['kode' => 'perpanjang-masa-aktif', 'nama' => 'Perpanjang masa aktif', 'deskripsi' => '+3 bulan dari masa aktif Premium', 'harga' => 49000, 'multi' => true, 'butuh_premium' => true, 'efek' => ['perpanjang_bulan' => 3]],
            ['kode' => 'domain-kustom', 'nama' => 'Domain kustom', 'deskripsi' => 'Alamat sendiri, per tahun', 'harga' => 150000, 'satuan' => ' / tahun', 'efek' => ['fitur' => 'domain-kustom']],
            ['kode' => 'live-streaming', 'nama' => 'Live streaming', 'deskripsi' => 'Tombol siaran langsung di undangan', 'harga' => 99000, 'efek' => ['fitur' => 'live-streaming']],
            ['kode' => 'blast-whatsapp', 'nama' => 'Blast WhatsApp', 'deskripsi' => '500 pesan terjadwal ke tamu', 'harga' => 75000, 'efek' => ['fitur' => 'blast-whatsapp']],
            ['kode' => 'hapus-logo', 'nama' => 'Hapus logo undangyah.com', 'deskripsi' => 'Undangan tampil tanpa logo kami', 'harga' => 49000, 'termasuk_premium' => true, 'efek' => ['fitur' => 'tanpa-logo']],
            ['kode' => 'musik-premium', 'nama' => 'Musik premium', 'deskripsi' => 'Lebih dari 50 lagu pilihan', 'harga' => 29000, 'efek' => ['fitur' => 'musik-premium']],
            ['kode' => 'tema-eksklusif', 'nama' => 'Tema eksklusif', 'deskripsi' => 'Akses template Deluxe tanpa ganti paket', 'harga' => 39000, 'efek' => ['fitur' => 'tema-eksklusif']],
        ] as $i => $baris) {
            AddOn::firstOrCreate(['kode' => $baris['kode']], $baris + ['urutan' => $i + 1, 'aktif' => true]);
        }

        // Contoh voucher (tanggal berlaku mengikuti desain; HEMAT50K sengaja habis dan MERDEKA17 sengaja kedaluwarsa).
        foreach ([
            ['kode' => 'NIKAH20', 'jenis' => 'persen', 'nilai' => 20, 'batas_pakai' => 100, 'terpakai' => 64, 'berlaku_sampai' => '2026-12-31 23:59:59', 'paket_berlaku' => null],
            ['kode' => 'PREMIUM10', 'jenis' => 'persen', 'nilai' => 10, 'batas_pakai' => 500, 'terpakai' => 88, 'berlaku_sampai' => '2027-03-31 23:59:59', 'paket_berlaku' => ['premium']],
            ['kode' => 'HEMAT50K', 'jenis' => 'nominal', 'nilai' => 50000, 'batas_pakai' => 50, 'terpakai' => 50, 'berlaku_sampai' => '2026-11-30 23:59:59', 'paket_berlaku' => null],
            ['kode' => 'MERDEKA17', 'jenis' => 'persen', 'nilai' => 17, 'batas_pakai' => 200, 'terpakai' => 121, 'berlaku_sampai' => '2026-08-31 23:59:59', 'paket_berlaku' => null],
        ] as $baris) {
            Voucher::firstOrCreate(['kode' => $baris['kode']], $baris + ['aktif' => true]);
        }

        MetodePembayaran::firstOrCreate(['kode' => 'transfer-bank'], [
            'label' => 'Transfer manual',
            'keterangan' => 'Transfer ke rekening kami, lalu unggah bukti',
            'tipe' => MetodePembayaran::MANUAL,
            'kode_unik' => true,
            'urutan' => 1,
            'aktif' => true,
            'data' => [
                'placeholder' => true,
                'bank' => 'BANK CONTOH',
                'nomor' => '0000000000',
                'atas' => 'GANTI DI ADMIN: NAMA PEMILIK REKENING',
                'instruksi' => 'Silakan lakukan transfer ke rekening berikut ini.',
                'langkah' => [
                    'Buka aplikasi mobile banking atau ATM.',
                    'Transfer sesuai nominal (termasuk kode unik) ke rekening di atas.',
                    'Simpan bukti transfer, lalu unggah di bagian Bukti pembayaran.',
                    'Tunggu verifikasi admin. Paketmu aktif setelah pembayaran dikonfirmasi.',
                ],
            ],
        ]);
        MetodePembayaran::firstOrCreate(['kode' => 'dompet-digital'], [
            'label' => 'Dompet digital (transfer manual)',
            'keterangan' => 'Kirim ke nomor e-wallet kami, lalu unggah bukti',
            'tipe' => MetodePembayaran::INSTRUKSI,
            'kode_unik' => true,
            'urutan' => 2,
            'aktif' => false, // belum diisi admin
            'data' => [
                'placeholder' => true,
                'tujuan_label' => 'Nomor e-wallet',
                'tujuan_nilai' => '0000000000',
                'atas' => 'GANTI DI ADMIN: NAMA PEMILIK AKUN',
                'instruksi' => 'Kirim sesuai nominal ke nomor e-wallet berikut.',
                'langkah' => [
                    'Buka aplikasi e-wallet.',
                    'Kirim uang sesuai nominal (termasuk kode unik) ke nomor di atas.',
                    'Simpan bukti, lalu unggah di bagian Bukti pembayaran.',
                ],
            ],
        ]);

        // Sakelar kuota paket dan jam kedaluwarsa pesanan: hanya bila belum pernah diatur (jangan menimpa pilihan admin).
        if (Pengaturan::ambil('paket.aktif') === null) {
            Pengaturan::simpan('paket', array_merge((array) Pengaturan::ambil('paket', []), ['aktif' => true]));
        }
        if (Pengaturan::ambil('pembayaran.kedaluwarsa_jam') === null) {
            Pengaturan::simpan('pembayaran', array_merge((array) Pengaturan::ambil('pembayaran', []), ['kedaluwarsa_jam' => 24]));
        }
        app(SinkronKuotaPaket::class)->sinkronkan();
    }
}
