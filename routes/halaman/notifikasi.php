<?php

use App\Livewire\Notifikasi\Daftar;
use Illuminate\Support\Facades\Route;

Route::get('/notifikasi', Daftar::class)->name('notifikasi');
