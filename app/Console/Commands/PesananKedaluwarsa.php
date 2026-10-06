<?php

namespace App\Console\Commands;

use App\Services\KonfirmasiPesanan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('undangyah:pesanan-kedaluwarsa')]
#[Description('Tandai pesanan menunggu yang lewat batas waktu sebagai kedaluwarsa dan kembalikan voucher yang ditahan')]
class PesananKedaluwarsa extends Command
{
    public function handle(KonfirmasiPesanan $konfirmasi): int
    {
        $jumlah = $konfirmasi->sapuKedaluwarsa();
        $this->info($jumlah.' pesanan ditandai kedaluwarsa.');

        return self::SUCCESS;
    }
}
