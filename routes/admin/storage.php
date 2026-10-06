<?php

use App\Livewire\Admin\Penyimpanan;
use Illuminate\Support\Facades\Route;

Route::get('/storage', Penyimpanan::class)->name('storage');
