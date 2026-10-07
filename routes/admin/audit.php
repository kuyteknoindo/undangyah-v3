<?php

use App\Livewire\Admin\LogAudit;
use Illuminate\Support\Facades\Route;

Route::get('/audit', LogAudit::class)->name('audit');
