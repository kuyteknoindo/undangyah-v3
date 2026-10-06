<?php

use App\Http\Controllers\UndanganPublikController;
use App\Livewire\SyaratPrivasi;
use Illuminate\Support\Facades\Route;

/*
 * Rute publik, dimuat paling akhir oleh routes/web.php: rute aplikasi selalu menang.
 * Slug mengikuti SlugUndangan (huruf/angka dan tanda hubung, 3-60 karakter); huruf besar dialihkan ke huruf kecil.
 * Kata terlarang dan alamat tak dikenal (termasuk draf) jatuh ke halaman 404.
 */
$pola = '(?=.{3,60}$)[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*';

// Dokumen hukum terbuka untuk umum. Harus sebelum /{slug}; kata 'syarat' dan 'privasi' sudah terlarang sebagai slug.
Route::get('/syarat', SyaratPrivasi::class)->defaults('dokumen', 'syarat')->name('syarat');
Route::get('/privasi', SyaratPrivasi::class)->defaults('dokumen', 'privasi')->name('privasi');

Route::get('/{slug}', [UndanganPublikController::class, 'tampil'])->where('slug', $pola)->name('undangan.publik');
Route::get('/{slug}/ucapan', [UndanganPublikController::class, 'daftarUcapan'])->where('slug', $pola)->name('undangan.publik.ucapan.daftar');
Route::post('/{slug}/ucapan', [UndanganPublikController::class, 'kirimUcapan'])->where('slug', $pola)->name('undangan.publik.ucapan');
Route::post('/{slug}/rsvp', [UndanganPublikController::class, 'kirimRsvp'])->where('slug', $pola)->name('undangan.publik.rsvp');
