<?php

use App\Livewire\RiwayatKirim;
use Illuminate\Support\Facades\Route;

Route::get('/riwayat-kirim', RiwayatKirim::class)->name('riwayat-kirim');
