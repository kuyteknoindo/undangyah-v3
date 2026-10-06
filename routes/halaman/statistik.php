<?php

use App\Livewire\Statistik;
use Illuminate\Support\Facades\Route;

Route::get('/statistik/{undangan?}', Statistik::class)->whereNumber('undangan')->name('statistik');
