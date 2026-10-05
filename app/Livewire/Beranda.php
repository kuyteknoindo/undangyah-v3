<?php

namespace App\Livewire;

use Livewire\Component;

class Beranda extends Component
{
    public function render()
    {
        $nama = auth()->user()->namaDepan();
        $jam = (int) now()->format('G');
        $sapaan = match (true) {
            $jam < 11 => 'Selamat pagi',
            $jam < 15 => 'Selamat siang',
            $jam < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        return view('livewire.beranda')->layout('layouts::app', [
            'judul' => "{$sapaan}, {$nama}",
            'sub' => 'Ini ringkasan undanganmu hari ini.',
        ]);
    }
}
