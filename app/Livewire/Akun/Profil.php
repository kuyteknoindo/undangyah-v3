<?php

namespace App\Livewire\Akun;

use App\Services\RingkasanAkun;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Profil extends Component
{
    /** Dialog konfirmasi keluar dan panduan pasang aplikasi; dibuka/ditutup di sisi klien. */
    public bool $konfirmasiKeluar = false;

    public bool $panduanPasang = false;

    /** Rute pertama yang sudah terdaftar dari daftar calon; null bila halamannya belum ada (tampil "Segera"). */
    private function rute(string ...$calon): ?string
    {
        foreach ($calon as $nama) {
            if (Route::has($nama)) {
                return route($nama);
            }
        }

        return null;
    }

    public function render(RingkasanAkun $ringkasan)
    {
        $user = auth()->user();
        $paket = $ringkasan->paket($user);
        $belumDibaca = $ringkasan->notifikasiBelumDibaca($user);
        $templatePesan = $ringkasan->jumlahTemplatePesan($user);
        $terkirim = $ringkasan->jumlahPesanTerkirim($user);

        $subPaket = match (true) {
            $paket['kode'] === 'gratis' => 'Fitur dasar untuk memulai',
            $paket['berakhir'] => 'Berakhir '.$paket['sampai']?->locale('id')->translatedFormat('j M Y'),
            default => 'Berlaku sampai '.$paket['sampai']?->locale('id')->translatedFormat('j M Y')
                .(($paket['sisaHari'] ?? 99) <= 30 ? ' ('.$paket['sisaHari'].' hari lagi)' : ''),
        };

        $kelompok = [
            ['judul' => 'Akun', 'item' => [
                ['label' => 'Informasi Akun', 'sub' => $user->email.($user->phone ? ' · '.$user->teleponTampil() : ''), 'ikon' => 'akun-info', 'href' => route('akun.informasi')],
                ['label' => 'Ubah Kata Sandi', 'sub' => $ringkasan->teksSandiTerakhirDiubah($user), 'ikon' => 'gembok', 'href' => route('akun.sandi')],
                ['label' => 'Notifikasi', 'sub' => $belumDibaca ? $belumDibaca.' belum dibaca' : 'Semua sudah dibaca', 'ikon' => 'lonceng', 'href' => $this->rute('notifikasi'), 'lencana' => $belumDibaca],
            ]],
            ['judul' => 'Undangan dan pengiriman', 'item' => [
                ['label' => 'Link Kustom', 'sub' => $ringkasan->tautanUndangan($user) ?? 'Atur alamat undangan', 'ikon' => 'akun-tautan', 'href' => $this->rute('link-kustom', 'link', 'tautan')],
                ['label' => 'Template Pesan Saya', 'sub' => $templatePesan ? $templatePesan.' template tersimpan' : 'Belum ada template tersimpan', 'ikon' => 'akun-pesan', 'href' => $this->rute('template-pesan', 'pesan')],
                ['label' => 'Riwayat Pengiriman', 'sub' => $terkirim ? $terkirim.' pesan terkirim' : 'Belum ada pesan terkirim', 'ikon' => 'akun-riwayat', 'href' => $this->rute('riwayat-kirim', 'riwayat')],
            ]],
            ['judul' => 'Langganan dan bantuan', 'item' => [
                ['label' => 'Paket & Billing', 'sub' => 'Paket '.$paket['nama'].($paket['berakhir'] ? ' (berakhir)' : ''), 'ikon' => 'billing', 'href' => $this->rute('billing')],
                ['label' => 'Pusat Bantuan', 'sub' => null, 'ikon' => 'bantuan', 'href' => $this->rute('bantuan')],
                ['label' => 'Hubungi Layanan Pelanggan', 'sub' => null, 'ikon' => 'akun-headset', 'href' => $this->rute('hubungi')],
                ['label' => 'Syarat & Kebijakan Privasi', 'sub' => null, 'ikon' => 'akun-dokumen', 'href' => $this->rute('syarat', 'syarat-privasi', 'privasi')],
            ]],
        ];

        return view('livewire.akun.profil', [
            'user' => $user,
            'statistik' => $ringkasan->statistik($user),
            'paket' => $paket,
            'subPaket' => $subPaket,
            'kelompok' => $kelompok,
            'bergabung' => $user->created_at?->locale('id')->translatedFormat('j M Y'),
        ])->layout('layouts::app', ['judul' => 'Profil', 'kecil' => 'Akun']);
    }
}
