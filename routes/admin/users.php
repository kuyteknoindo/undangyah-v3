<?php

use App\Livewire\Admin\Users;
use Illuminate\Support\Facades\Route;

Route::get('/users', Users::class)->name('users');
