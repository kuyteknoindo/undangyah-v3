<?php

use App\Livewire\Auth\Daftar;
use App\Livewire\Auth\LupaSandi;
use App\Livewire\Auth\Masuk;
use App\Livewire\Auth\VerifikasiEmail;
use App\Livewire\Beranda;
use App\Livewire\Segera;
use App\Support\Navigasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Susunan rute:
 *  - routes/halaman/*.php : halaman pengguna (di dalam grup auth + verified). Satu berkas per fitur.
 *  - routes/admin/*.php   : halaman Panel Admin (di dalam grup peran:admin, prefix /admin, nama admin.*).
 *  - routes/publik.php    : rute publik, dimuat PALING AKHIR (undangan tamu /{slug} adalah rute tangkap-semua).
 * Menu di sidebar yang rutenya belum dibuat tampil sebagai halaman "Segera" (placeholder).
 */

Route::redirect('/', '/beranda');
Route::view('/offline', 'halaman.offline')->name('offline');

// Tamu (belum masuk)
Route::middleware('guest')->group(function () {
    Route::get('/masuk', Masuk::class)->name('masuk');
    Route::get('/daftar', Daftar::class)->name('daftar');
    Route::get('/lupa-sandi', LupaSandi::class)->name('lupa-sandi');
});

$sudahAda = fn (string $nama): bool => collect(Route::getRoutes()->getRoutes())->contains(fn ($r) => $r->getName() === $nama);

// Sudah masuk
Route::middleware('auth')->group(function () use ($sudahAda) {
    Route::post('/keluar', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    })->name('keluar');

    // Nama rute `verification.notice` dipakai middleware `verified` bawaan Laravel.
    Route::get('/verifikasi-email', VerifikasiEmail::class)->name('verification.notice');

    // Aplikasi: hanya untuk akun dengan email terverifikasi.
    Route::middleware('verified')->group(function () use ($sudahAda) {
        Route::get('/beranda', Beranda::class)->name('beranda');

        foreach (glob(base_path('routes/halaman/*.php')) ?: [] as $berkas) {
            require $berkas;
        }

        // Panel Admin: hanya peran admin.
        Route::middleware('peran:admin')->prefix('admin')->name('admin.')->group(function () use ($sudahAda) {
            foreach (glob(base_path('routes/admin/*.php')) ?: [] as $berkas) {
                require $berkas;
            }

            foreach (Navigasi::admin() as $grup) {
                foreach ($grup['item'] as $item) {
                    if (! $sudahAda('admin.'.$item['slug'])) {
                        Route::get('/'.$item['slug'], Segera::class)->name($item['slug'])
                            ->defaults('judul', $item['label'])->defaults('admin', true);
                    }
                }
            }
        });

        // Halaman pengguna yang belum dibuat -> placeholder.
        foreach (['edit-undangan', 'rsvp', 'bagikan', 'template', 'buku-tamu', 'hadiah', 'statistik', 'billing', 'akun', 'bantuan', 'hubungi', 'notifikasi', 'buat-undangan'] as $rute) {
            if (! $sudahAda($rute)) {
                Route::get('/'.$rute, Segera::class)->name($rute)
                    ->defaults('judul', Navigasi::judul($rute) ?? Str::headline($rute));
            }
        }
    });
});

if (file_exists(base_path('routes/publik.php'))) {
    require base_path('routes/publik.php');
}
