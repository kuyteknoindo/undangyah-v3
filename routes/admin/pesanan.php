<?php

use App\Livewire\Admin\DataPesanan;
use App\Livewire\Admin\Invoices;
use Illuminate\Support\Facades\Route;

Route::get('/pesanan', DataPesanan::class)->name('pesanan');
Route::get('/invoices', Invoices::class)->name('invoices');
