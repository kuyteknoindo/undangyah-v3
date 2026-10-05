<?php

use App\Livewire\Beranda;
use App\Livewire\Segera;
use App\Support\Navigasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::redirect('/', '/beranda');

// Sementara (tahap 1): halaman masuk diganti komponen Livewire di tahap 2.
Route::get('/masuk', fn () => 'Halaman masuk dibuat di tahap 2.')->middleware('guest')->name('masuk');

Route::middleware('auth')->group(function () {
    Route::post('/keluar', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    })->name('keluar');

    Route::get('/beranda', Beranda::class)->name('beranda');

    // Halaman pengguna yang dibuat di tahap berikutnya.
    foreach (['edit-undangan', 'rsvp', 'bagikan', 'template', 'buku-tamu', 'hadiah', 'statistik', 'billing', 'akun', 'bantuan', 'notifikasi', 'buat-undangan'] as $rute) {
        Route::get('/'.$rute, Segera::class)->name($rute)
            ->defaults('judul', Navigasi::judul($rute) ?? Str::headline($rute));
    }

    // Panel Admin: hanya peran admin.
    Route::middleware('peran:admin')->prefix('admin')->name('admin.')->group(function () {
        foreach (Navigasi::admin() as $grup) {
            foreach ($grup['item'] as $item) {
                Route::get('/'.$item['slug'], Segera::class)->name($item['slug'])
                    ->defaults('judul', $item['label'])->defaults('admin', true);
            }
        }
    });
});
