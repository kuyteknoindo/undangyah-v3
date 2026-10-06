<?php

use App\Http\Controllers\Admin\UnduhBackupController;
use App\Livewire\Admin\BackupRestore;
use Illuminate\Support\Facades\Route;

Route::get('/backup', BackupRestore::class)->name('backup');
Route::get('/backup/{backup}/unduh', UnduhBackupController::class)->whereNumber('backup')->name('backup.unduh');
