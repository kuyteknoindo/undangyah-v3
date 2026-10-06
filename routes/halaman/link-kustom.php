<?php

use App\Livewire\LinkKustom;
use Illuminate\Support\Facades\Route;

Route::get('/link-kustom/{undangan?}', LinkKustom::class)->whereNumber('undangan')->name('link-kustom');
