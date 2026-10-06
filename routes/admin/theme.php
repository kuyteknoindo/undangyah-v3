<?php

use App\Livewire\Admin\Theme;
use Illuminate\Support\Facades\Route;

// Daftar tema; ?tema=<id|baru> membuka editor di halaman yang sama (menu sidebar tetap menyala).
Route::get('/theme', Theme::class)->name('theme');
