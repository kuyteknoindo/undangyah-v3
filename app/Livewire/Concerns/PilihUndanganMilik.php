<?php

namespace App\Livewire\Concerns;

use App\Models\Undangan;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;

/**
 * Pemilihan undangan target untuk halaman Buku Tamu, Statistik, dan Hadiah. Id dikunci dari klien dan
 * selalu dicek kepemilikannya di server: undangan orang lain = 404.
 */
trait PilihUndanganMilik
{
    #[Locked]
    public ?int $undanganId = null;

    /** Undangan awal: yang diminta lewat rute/query (wajib milik sendiri), kalau tidak terbit terbaru, lalu terbaru. */
    protected function tentukanUndanganAwal(?string $diminta = null): void
    {
        $diminta ??= is_scalar(request()->query('undangan')) ? (string) request()->query('undangan') : null;
        $milik = Undangan::query()->where('user_id', auth()->id());

        if ($diminta !== null && $diminta !== '') {
            abort_unless(ctype_digit($diminta), 404);
            $this->undanganId = (clone $milik)->findOrFail((int) $diminta)->id;

            return;
        }

        $this->undanganId = (clone $milik)->where('status', Undangan::TERBIT)->latest('updated_at')->latest('id')->value('id')
            ?? $milik->latest('updated_at')->latest('id')->value('id');
    }

    /** Dipanggil dari tampilan lewat wire:change; id asing ditolak 404. */
    public function pilihUndangan(int $id): void
    {
        $milik = Undangan::query()->where('user_id', auth()->id())->find($id);
        abort_unless($milik, 404);
        $this->undanganId = $milik->id;
        $this->setelahPilihUndangan();
    }

    /** Titik kait: setel ulang status halaman bila undangan berganti. */
    protected function setelahPilihUndangan(): void {}

    protected function undanganTerpilih(): ?Undangan
    {
        return $this->undanganId ? Undangan::query()->where('user_id', auth()->id())->find($this->undanganId) : null;
    }

    protected function undanganWajib(): Undangan
    {
        $u = $this->undanganTerpilih();
        abort_unless($u, 404);

        return $u;
    }

    /** @return Collection<int,Undangan> */
    protected function daftarUndangan(): Collection
    {
        return Undangan::query()->where('user_id', auth()->id())->latest('updated_at')->latest('id')->get(['id', 'judul', 'slug', 'status', 'tanggal_acara']);
    }
}
