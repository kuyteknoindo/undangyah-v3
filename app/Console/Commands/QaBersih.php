<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('undangyah:qa-bersih')]
#[Description('[hanya lokal] Hapus semua akun uji qa-*@example.test beserta sesi dan OTP-nya')]
class QaBersih extends Command
{
    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Hanya untuk APP_ENV=local.');

            return self::FAILURE;
        }
        $ids = User::where('email', 'like', 'qa-%@example.test')->pluck('id');
        DB::table('sessions')->whereIn('user_id', $ids)->delete();
        DB::table('otp_codes')->where('email', 'like', 'qa-%@example.test')->delete();
        // Data turunan (undangan, tamu, dst.) ikut terhapus lewat foreign key cascade.
        $n = User::whereIn('id', $ids)->delete();
        $this->info("{$n} akun uji dihapus.");

        return self::SUCCESS;
    }
}
