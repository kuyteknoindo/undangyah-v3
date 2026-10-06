<?php

use App\Livewire\Admin\Pembayaran;
use Illuminate\Support\Facades\Route;

Route::get('/pembayaran', Pembayaran::class)->name('pembayaran');
