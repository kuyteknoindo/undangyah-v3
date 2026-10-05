<?php

namespace App\Support;

/**
 * Struktur menu sidebar, mengikuti desain desktop (Main.dc.html).
 * Tiap item: label, rute, ikon, opsional badge.
 */
class Navigasi
{
    /** @return list<array{label:string,route:string,ikon:string,badge?:string}> */
    public static function menu(): array
    {
        return [
            ['label' => 'Beranda', 'route' => 'beranda', 'ikon' => 'beranda'],
            ['label' => 'Edit Undangan', 'route' => 'edit-undangan', 'ikon' => 'edit'],
            ['label' => 'RSVP & Tamu', 'route' => 'rsvp', 'ikon' => 'rsvp'],
            ['label' => 'Bagikan Undangan', 'route' => 'bagikan', 'ikon' => 'bagikan'],
            ['label' => 'Template Desain', 'route' => 'template', 'ikon' => 'template', 'badge' => 'BARU'],
            ['label' => 'Buku Tamu', 'route' => 'buku-tamu', 'ikon' => 'buku'],
            ['label' => 'Hadiah Digital', 'route' => 'hadiah', 'ikon' => 'hadiah'],
            ['label' => 'Statistik', 'route' => 'statistik', 'ikon' => 'statistik'],
        ];
    }

    /** @return list<array{label:string,route:string,ikon:string}> */
    public static function lainnya(): array
    {
        return [
            ['label' => 'Paket & Billing', 'route' => 'billing', 'ikon' => 'billing'],
            ['label' => 'Pengaturan Akun', 'route' => 'akun', 'ikon' => 'akun'],
            ['label' => 'Pusat Bantuan', 'route' => 'bantuan', 'ikon' => 'bantuan'],
        ];
    }

    /**
     * Menu Panel Admin: 4 grup, 14 halaman (sesuai desain desktop).
     *
     * @return list<array{judul:string,item:list<array{label:string,slug:string,ikon:string}>}>
     */
    public static function admin(): array
    {
        return [
            ['judul' => 'Konten', 'item' => [
                ['label' => 'Theme Creator', 'slug' => 'theme', 'ikon' => 'admin-theme'],
                ['label' => 'Media', 'slug' => 'media', 'ikon' => 'admin-media'],
                ['label' => 'Musik', 'slug' => 'musik', 'ikon' => 'admin-music'],
            ]],
            ['judul' => 'Penjualan', 'item' => [
                ['label' => 'Product', 'slug' => 'product', 'ikon' => 'admin-product'],
                ['label' => 'Addons Product', 'slug' => 'addons', 'ikon' => 'admin-addons'],
                ['label' => 'Voucher', 'slug' => 'voucher', 'ikon' => 'admin-voucher'],
                ['label' => 'Data Pesanan', 'slug' => 'pesanan', 'ikon' => 'admin-order'],
                ['label' => 'Invoices', 'slug' => 'invoices', 'ikon' => 'admin-invoice'],
                ['label' => 'Metode Pembayaran', 'slug' => 'pembayaran', 'ikon' => 'admin-payment'],
            ]],
            ['judul' => 'Pengguna', 'item' => [
                ['label' => 'Users', 'slug' => 'users', 'ikon' => 'admin-users'],
                ['label' => 'Notifikasi', 'slug' => 'notifikasi', 'ikon' => 'admin-notif'],
                ['label' => 'OTP Setting', 'slug' => 'otp', 'ikon' => 'admin-otp'],
            ]],
            ['judul' => 'Sistem', 'item' => [
                ['label' => 'Storage', 'slug' => 'storage', 'ikon' => 'admin-storage'],
                ['label' => 'Backup dan Restore', 'slug' => 'backup', 'ikon' => 'admin-backup'],
            ]],
        ];
    }

    /** Judul halaman menurut nama rute (untuk placeholder dan header). */
    public static function judul(string $route): ?string
    {
        foreach ([...self::menu(), ...self::lainnya()] as $item) {
            if ($item['route'] === $route) {
                return $item['label'];
            }
        }
        foreach (self::admin() as $grup) {
            foreach ($grup['item'] as $item) {
                if ('admin.'.$item['slug'] === $route) {
                    return $item['label'];
                }
            }
        }

        return null;
    }
}
