<?php

use App\Livewire\Admin\Notifikasi;
use Illuminate\Support\Facades\Route;

Route::get('/notifikasi', Notifikasi::class)->name('notifikasi');
