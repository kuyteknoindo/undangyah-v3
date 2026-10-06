<?php

use App\Livewire\Admin\KatalogMusik;
use Illuminate\Support\Facades\Route;

Route::get('/musik', KatalogMusik::class)->name('musik');
