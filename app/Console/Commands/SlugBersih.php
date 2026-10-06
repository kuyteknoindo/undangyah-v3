<?php

namespace App\Console\Commands;

use App\Services\PenahananSlug;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('undangyah:slug-bersih')]
#[Description('Hapus penahanan slug undangan yang sudah lewat 90 hari')]
class SlugBersih extends Command
{
    public function handle(PenahananSlug $penahanan): int
    {
        $this->info($penahanan->bersihkan().' penahanan slug kedaluwarsa dihapus.');

        return self::SUCCESS;
    }
}
