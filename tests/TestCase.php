<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Livewire menyimpan "sudah merender komponen" / "paksa suntik aset" di properti statis yang hanya dikosongkan
        // oleh flush-state. Tanpa ini, halaman publik tanpa Livewire ikut disuntik skrip Livewire bila tes sebelumnya
        // (dalam proses yang sama) merender halaman aplikasi, sehingga hasil tes bergantung pada urutan.
        Livewire::flushState();
    }
}
