<?php

use App\Livewire\TemplatePesan;
use Illuminate\Support\Facades\Route;

Route::get('/template-pesan', TemplatePesan::class)->name('template-pesan');
