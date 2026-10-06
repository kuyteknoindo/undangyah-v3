<?php

namespace App\Jobs;

use App\Services\PengirimSiaran;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Memproses satu potongan siaran notifikasi, lalu mengantrekan dirinya lagi sampai semua penerima terlayani. */
class KirimSiaranNotifikasi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $siaranId) {}

    public function handle(PengirimSiaran $pengirim): void
    {
        if (! $pengirim->proses($this->siaranId)) {
            self::dispatch($this->siaranId);
        }
    }

    public function failed(?Throwable $e): void
    {
        app(PengirimSiaran::class)->tandaiGagal($this->siaranId);
    }
}
