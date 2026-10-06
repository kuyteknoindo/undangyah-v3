<?php

use App\Livewire\Bagikan;
use Illuminate\Support\Facades\Route;

Route::get('/bagikan', Bagikan::class)->name('bagikan');
