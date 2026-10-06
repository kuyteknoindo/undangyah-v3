<?php

use App\Livewire\PusatBantuan;
use Illuminate\Support\Facades\Route;

Route::get('/bantuan', PusatBantuan::class)->name('bantuan');
