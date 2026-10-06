<?php

use App\Livewire\HadiahDigital;
use Illuminate\Support\Facades\Route;

Route::get('/hadiah/{undangan?}', HadiahDigital::class)->whereNumber('undangan')->name('hadiah');
