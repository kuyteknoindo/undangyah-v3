<?php

use App\Livewire\Admin\PustakaMedia;
use Illuminate\Support\Facades\Route;

Route::get('/media', PustakaMedia::class)->name('media');
