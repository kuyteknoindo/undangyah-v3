<?php

use App\Livewire\EditorUndangan;
use Illuminate\Support\Facades\Route;

// Tanpa parameter (menu sidebar): komponen memilih undangan terbaru milik pengguna atau mengarahkan ke wizard.
Route::get('/edit-undangan/{undangan?}', EditorUndangan::class)->whereNumber('undangan')->name('edit-undangan');
