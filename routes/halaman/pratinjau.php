<?php

use App\Http\Controllers\PratinjauBingkaiController;
use App\Livewire\Pratinjau;
use Illuminate\Support\Facades\Route;

// Bingkai berisi HTML undangan murni (dimuat di iframe halaman pratinjau). Didaftarkan lebih dulu agar "bingkai" bukan dibaca sebagai id.
Route::get('/pratinjau/bingkai/contoh', [PratinjauBingkaiController::class, 'contoh'])->name('pratinjau.bingkai.contoh');
Route::get('/pratinjau/bingkai/{undangan}', [PratinjauBingkaiController::class, 'undangan'])->whereNumber('undangan')->name('pratinjau.bingkai');
Route::get('/pratinjau/{undangan?}', Pratinjau::class)->whereNumber('undangan')->name('pratinjau');
