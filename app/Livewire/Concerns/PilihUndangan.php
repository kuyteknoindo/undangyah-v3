<?php

namespace App\Livewire\Concerns;

use App\Models\Undangan;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

/** Pemilihan undangan target milik pengguna login (halaman pengiriman). Milik orang lain selalu 404. */
trait PilihUndangan
{
    #[Locked]
    public ?int $undanganId = null;

    /** Id undangan dari parameter mount atau query `?undangan=` (rute tanpa parameter path). */
    protected function pilihAwal(?string $undangan): void
    {
        $undangan ??= request()->query('undangan');
        abort_if($undangan !== null && ! (is_string($undangan) && ctype_digit($undangan)), 404);
        $milik = Undangan::where('user_id', auth()->id());
        if ($undangan !== null) {
            $dipilih = (clone $milik)->find((int) $undangan);
            abort_unless($dipilih, 404);
        } else {
            $dipilih = (clone $milik)->where('status', Undangan::TERBIT)->latest('diterbitkan_at')->latest('id')->first()
                ?? $milik->latest('updated_at')->latest('id')->first();
        }
        $this->undanganId = $dipilih?->id;
    }

    protected function undanganTerpilih(): ?Undangan
    {
        return $this->undanganId ? Undangan::where('user_id', auth()->id())->find($this->undanganId) : null;
    }

    /** @return Collection<int,Undangan> */
    protected function daftarUndangan()
    {
        return Undangan::where('user_id', auth()->id())->orderByDesc('updated_at')->orderByDesc('id')->get(['id', 'judul', 'status', 'slug']);
    }
}
