<?php

use App\Models\KodeOtp;
use App\Services\PengaturanBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan kode OTP lama (jadwal dijalankan oleh cron user deploy: schedule:run tiap menit).
Schedule::command('model:prune', ['--model' => [KodeOtp::class]])->daily();

// Pesanan menunggu yang lewat batas bayar ditandai kedaluwarsa dan voucher yang ditahan dikembalikan.
Schedule::command('undangyah:pesanan-kedaluwarsa')->everyFiveMinutes()->withoutOverlapping();

// Backup otomatis: DEFAULT NONAKTIF; admin mengaktifkannya di Admin > Backup dan Restore. Frekuensi dicek saat dijalankan.
Schedule::command('undangyah:backup --otomatis')->dailyAt('02:00')->withoutOverlapping(120)
    ->when(fn () => PengaturanBackup::jalanHariIni());
