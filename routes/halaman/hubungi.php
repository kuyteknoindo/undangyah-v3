<?php

use App\Livewire\HubungiLayanan;
use Illuminate\Support\Facades\Route;

Route::get('/hubungi', HubungiLayanan::class)->name('hubungi');
