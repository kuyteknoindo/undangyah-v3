<?php

use App\Livewire\Admin\AddOns;
use Illuminate\Support\Facades\Route;

Route::get('/addons', AddOns::class)->name('addons');
