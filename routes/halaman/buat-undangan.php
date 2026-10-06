<?php

use App\Livewire\BuatUndangan;
use Illuminate\Support\Facades\Route;

Route::get('/buat-undangan', BuatUndangan::class)->name('buat-undangan');
