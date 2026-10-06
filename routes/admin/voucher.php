<?php

use App\Livewire\Admin\Vouchers;
use Illuminate\Support\Facades\Route;

Route::get('/voucher', Vouchers::class)->name('voucher');
