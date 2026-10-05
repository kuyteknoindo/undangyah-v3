<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('undangyah:qa-user {email : harus qa-*@example.test} {--admin : jadikan admin}')]
#[Description('[hanya lokal] Buat/segarkan akun uji QA terverifikasi dengan kata sandi QaSandi12345')]
class QaUser extends Command
{
    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Hanya untuk APP_ENV=local.');

            return self::FAILURE;
        }
        $email = (string) $this->argument('email');
        if (! preg_match('/^qa-[a-z0-9._-]+@example\.test$/', $email)) {
            $this->error('Email uji harus berbentuk qa-xxx@example.test.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => 'QA '.strtoupper(substr(md5($email), 0, 4)), 'password' => 'QaSandi12345']);
        $user->forceFill(['role' => $this->option('admin') ? User::PERAN_ADMIN : User::PERAN_USER, 'email_verified_at' => now()])->save();
        $this->info("Akun uji siap: {$email}");

        return self::SUCCESS;
    }
}
