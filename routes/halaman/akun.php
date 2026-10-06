<?php

use App\Http\Controllers\KonfirmasiEmailController;
use App\Livewire\Akun\EditProfil;
use App\Livewire\Akun\InformasiAkun;
use App\Livewire\Akun\Profil;
use App\Livewire\Akun\UbahSandi;
use Illuminate\Support\Facades\Route;

Route::get('/akun', Profil::class)->name('akun');
Route::get('/akun/ubah', EditProfil::class)->name('akun.ubah');
Route::get('/akun/informasi', InformasiAkun::class)->name('akun.informasi');
Route::get('/akun/sandi', UbahSandi::class)->name('akun.sandi');

// Tautan konfirmasi email baru: bertanda tangan dan kedaluwarsa, tanpa syarat sudah masuk
// (tautan sering dibuka dari peramban lain; bukti kepemilikan adalah kotak masuk email baru).
Route::get('/akun/email/konfirmasi/{id}', KonfirmasiEmailController::class)
    ->whereNumber('id')
    ->middleware(['signed', 'throttle:20,1'])
    ->withoutMiddleware(['auth', 'verified'])
    ->name('akun.email.konfirmasi');
