<?php

use App\Livewire\Admin\Product;
use Illuminate\Support\Facades\Route;

Route::get('/product', Product::class)->name('product');
