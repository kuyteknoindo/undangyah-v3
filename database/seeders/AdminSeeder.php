<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Membuat akun admin awal (phentem@gmail.com) dengan kata sandi acak.
 * Kata sandi HANYA ditulis ke berkas ber-izin 600 (ADMIN_LOGIN_FILE), tidak dicetak dan tidak masuk log/git.
 * Idempoten: bila akun sudah ada, tidak ada yang diubah (kata sandi tidak diputar ulang).
 */
class AdminSeeder extends Seeder
{
    public const EMAIL = 'phentem@gmail.com';

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            return;
        }

        $sandi = Str::password(20, symbols: false);

        $admin = new User(['name' => 'Deni', 'email' => self::EMAIL, 'password' => $sandi]);
        $admin->forceFill(['role' => User::PERAN_ADMIN, 'email_verified_at' => now()])->save();

        $berkas = config('undangyah.admin_login_file');
        if ($berkas) {
            $lama = umask(0o177);
            file_put_contents($berkas, 'Situs: '.config('app.url')."/masuk\nEmail: ".self::EMAIL."\nKata sandi: {$sandi}\n");
            umask($lama);
            @chmod($berkas, 0o600);
        }
    }
}
