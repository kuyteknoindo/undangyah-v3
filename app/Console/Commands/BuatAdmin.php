<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('undangyah:admin {email : Email akun} {--nama= : Nama (untuk akun baru)} {--sandi= : Kata sandi (acak bila dikosongkan, untuk akun baru)}')]
#[Description('Buat akun admin baru, atau naikkan akun yang sudah ada menjadi admin')]
class BuatAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill(['role' => User::PERAN_ADMIN, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            $this->info("Akun {$email} sekarang admin.");

            return self::SUCCESS;
        }

        $sandi = (string) ($this->option('sandi') ?: Str::password(16, symbols: false));
        $user = new User(['name' => $this->option('nama') ?: Str::before($email, '@'), 'email' => $email, 'password' => $sandi]);
        $user->forceFill(['role' => User::PERAN_ADMIN, 'email_verified_at' => now()])->save();

        $this->info("Admin dibuat: {$email}");
        if (! $this->option('sandi')) {
            $this->line("Kata sandi sementara: {$sandi}");
            $this->warn('Catat sekarang, lalu ganti setelah masuk. Kata sandi tidak disimpan di tempat lain.');
        }

        return self::SUCCESS;
    }
}
