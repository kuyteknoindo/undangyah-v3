<?php

namespace App\Livewire;

use App\Support\DokumenLegal;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Syarat & Ketentuan dan Kebijakan Privasi. Terbuka untuk umum (/syarat, /privasi); pengguna yang sudah masuk
 * dan terverifikasi melihatnya di dalam kerangka aplikasi (sidebar), tamu di kerangka dokumen sederhana.
 */
class SyaratPrivasi extends Component
{
    #[Locked]
    public string $dokumen = 'syarat';

    public function mount(string $dokumen = 'syarat'): void
    {
        abort_unless(DokumenLegal::valid($dokumen), 404);
        $this->dokumen = $dokumen;
    }

    public function render()
    {
        $dalamAplikasi = auth()->check() && auth()->user()->hasVerifiedEmail();
        $judul = DokumenLegal::JUDUL[$this->dokumen];

        return view('livewire.syarat.index', [
            'judul' => $judul,
            'bagian' => DokumenLegal::bagian($this->dokumen),
            'diperbarui' => DokumenLegal::diperbarui(),
            'contoh' => DokumenLegal::masihContoh(),
            'tautanHubungi' => $dalamAplikasi && Route::has('hubungi') ? route('hubungi') : null,
            'tautanBantuan' => $dalamAplikasi && Route::has('bantuan') ? route('bantuan') : null,
            'tautan' => ['syarat' => route('syarat'), 'privasi' => route('privasi')],
        ])->layout($dalamAplikasi ? 'layouts::app' : 'layouts::dokumen', [
            'judul' => $judul,
            'sub' => 'Bantuan',
            'indeks' => ! DokumenLegal::masihContoh(),
        ]);
    }
}
