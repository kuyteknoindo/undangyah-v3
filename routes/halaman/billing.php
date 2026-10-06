<?php

use App\Http\Controllers\BuktiPembayaranController;
use App\Http\Controllers\InvoiceController;
use App\Livewire\Checkout;
use App\Livewire\KonfirmasiPembayaran;
use App\Livewire\PaketBilling;
use Illuminate\Support\Facades\Route;

Route::get('/billing', PaketBilling::class)->name('billing');
Route::get('/checkout', Checkout::class)->name('checkout');
Route::get('/konfirmasi-pembayaran/{pesanan?}', KonfirmasiPembayaran::class)->where('pesanan', 'INV-[0-9]{4}-[0-9]{4,}')->name('konfirmasi-pembayaran');

// Dokumen milik pemilik pesanan (admin boleh membaca); pesanan orang lain = 404.
Route::get('/invoice/{pesanan}', InvoiceController::class)->where('pesanan', 'INV-[0-9]{4}-[0-9]{4,}')->middleware('throttle:60,1')->name('invoice');
Route::get('/pembayaran/bukti/{pesanan}', BuktiPembayaranController::class)->where('pesanan', 'INV-[0-9]{4}-[0-9]{4,}')->middleware('throttle:60,1')->name('pesanan.bukti');
