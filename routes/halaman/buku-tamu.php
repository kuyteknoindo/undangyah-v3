<?php

use App\Livewire\BukuTamu;
use Illuminate\Support\Facades\Route;

Route::get('/buku-tamu/{undangan?}', BukuTamu::class)->whereNumber('undangan')->name('buku-tamu');
