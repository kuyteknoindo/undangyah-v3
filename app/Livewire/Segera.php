<?php

namespace App\Livewire;

use Livewire\Component;

/** Halaman sementara untuk menu yang dibuat di tahap berikutnya. */
class Segera extends Component
{
    public string $judul = 'Halaman';

    public bool $admin = false;

    public function mount(string $judul = 'Halaman', bool $admin = false): void
    {
        $this->judul = $judul;
        $this->admin = $admin;
    }

    public function render()
    {
        return view('livewire.segera')->layout('layouts::app', [
            'judul' => $this->judul,
            'admin' => $this->admin,
        ]);
    }
}
