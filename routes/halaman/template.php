<?php

use App\Livewire\TemplateDesain;
use Illuminate\Support\Facades\Route;

Route::get('/template', TemplateDesain::class)->name('template');
