<?php

use App\Livewire\Admin\OtpSetting;
use Illuminate\Support\Facades\Route;

Route::get('/otp', OtpSetting::class)->name('otp');
