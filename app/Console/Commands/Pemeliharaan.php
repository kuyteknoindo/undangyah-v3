<?php

namespace App\Console\Commands;

use App\Models\Pengaturan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('undangyah:pemeliharaan {status : on atau off} {--pesan= : Pesan untuk pengguna} {--eta= : Perkiraan selesai, mis. "30 menit"}')]
#[Description('Nyalakan atau matikan mode pemeliharaan (admin tetap bisa masuk)')]
class Pemeliharaan extends Command
{
    public function handle(): int
    {
        $aktif = $this->argument('status') === 'on';
        Pengaturan::simpan('pemeliharaan', [
            'aktif' => $aktif,
            'pesan' => $this->option('pesan') ?: 'Kami sedang memperbarui layanan agar lebih baik. Mohon coba lagi sebentar lagi.',
            'eta' => $this->option('eta') ?: null,
            'mulai' => $aktif ? now()->toIso8601String() : null,
        ]);
        $this->info('Mode pemeliharaan '.($aktif ? 'AKTIF' : 'nonaktif').'.');

        return self::SUCCESS;
    }
}
