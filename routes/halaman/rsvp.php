<?php

use App\Livewire\RsvpTamu;
use Illuminate\Support\Facades\Route;

Route::get('/rsvp/{undangan?}', RsvpTamu::class)->whereNumber('undangan')->name('rsvp');
