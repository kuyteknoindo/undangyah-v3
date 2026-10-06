<?php

use App\Http\Controllers\AkhiriImpersonasiController;
use Illuminate\Support\Facades\Route;

// Dipakai tombol "Kembali ke Admin" di banner sesi "masuk sebagai" (lihat PengamanAkun).
Route::post('/akhiri-impersonasi', AkhiriImpersonasiController::class)->middleware('throttle:20,1')->name('impersonasi.akhiri');
