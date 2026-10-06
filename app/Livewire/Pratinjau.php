<?php

namespace App\Livewire;

use App\Models\Undangan;
use App\Services\PratinjauUndangan;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** "Lihat seperti tamu": pratinjau undangan milik sendiri dalam bingkai ponsel/tablet, versi terbit atau draf. */
class Pratinjau extends Component
{
    /** Hanya diubah dari mount()/pilihUndangan() yang memeriksa kepemilikan. */
    #[Locked]
    public ?int $undanganId = null;

    /** terbit | draf (relevan bila undangan terbit dan drafnya berbeda). */
    #[Url(as: 'v', except: '')]
    public string $versi = '';

    /** ponsel | tablet */
    #[Url(as: 'p', except: 'ponsel')]
    public string $perangkat = 'ponsel';

    /** Naik tiap "Muat ulang"; ikut ke URL bingkai supaya iframe dimuat ulang. */
    public int $rev = 1;

    public bool $contoh = false;

    public function mount(?string $undangan = null): void
    {
        if ($undangan !== null) {
            // Milik orang lain atau tidak ada -> 404 (tidak membedakan keduanya).
            $this->undanganId = $this->milik((int) $undangan)->id;

            return;
        }

        $this->undanganId = Undangan::query()->where('user_id', auth()->id())
            ->latest('updated_at')->latest('id')->value('id');
    }

    public function pilihUndangan(int $id): mixed
    {
        $u = $this->milik($id);

        return $this->redirectRoute('pratinjau', ['undangan' => $u->id], navigate: true);
    }

    public function pilihPerangkat(string $perangkat): void
    {
        $this->perangkat = $perangkat === 'tablet' ? 'tablet' : 'ponsel';
    }

    public function pilihVersi(string $versi): void
    {
        $this->versi = $versi === 'draf' ? 'draf' : 'terbit';
        $this->rev++;
    }

    public function muatUlang(): void
    {
        $this->rev++;
    }

    public function tampilkanContoh(): void
    {
        $this->contoh = true;
        $this->rev++;
    }

    public function sembunyikanContoh(): void
    {
        $this->contoh = false;
    }

    private function milik(int $id): Undangan
    {
        $undangan = Undangan::query()->where('user_id', auth()->id())->find($id);
        abort_unless($undangan, 404);

        return $undangan;
    }

    private function tautan(string $nama, array $param = []): string
    {
        return Route::has($nama) ? route($nama, $param) : '#';
    }

    public function render()
    {
        $daftar = Undangan::query()->where('user_id', auth()->id())
            ->latest('updated_at')->latest('id')->get(['id', 'judul', 'slug', 'status']);
        $u = $this->undanganId ? Undangan::query()->where('user_id', auth()->id())->find($this->undanganId) : null;

        $mode = 'tidak-ada';
        $versi = '';
        $adaDraf = false;
        $bingkai = null;
        $tautanTamu = null;
        $tautanHost = null;

        if ($u) {
            $info = PratinjauUndangan::tentukan($u, $this->versi);
            [$mode, $versi, $adaDraf] = [$info['mode'], $info['versi'], $info['adaDraf']];
            if ($mode !== 'kosong') {
                $bingkai = route('pratinjau.bingkai', ['undangan' => $u->id, 'v' => $versi === 'draf' ? 'draf' : null, 'r' => $this->rev]);
            }
            if ($u->slug) {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost();
                $tautanHost = $host.'/'.$u->slug;
                $tautanTamu = 'https://'.$tautanHost;
            }
        } elseif ($this->contoh) {
            $mode = 'contoh';
            $bingkai = route('pratinjau.bingkai.contoh', ['r' => $this->rev]);
        }

        return view('livewire.pratinjau', [
            'daftar' => $daftar,
            'u' => $u,
            'mode' => $mode,
            'versiAktif' => $versi,
            'adaDraf' => $adaDraf,
            'bingkai' => $bingkai,
            'tautanTamu' => $tautanTamu,
            'tautanHost' => $tautanHost,
            'tautanBuat' => $this->tautan('buat-undangan'),
            'tautanEdit' => $u ? $this->tautan('edit-undangan', ['undangan' => $u->id]) : '#',
            'tautanBagikan' => $u ? $this->tautan('bagikan', ['undangan' => $u->id]) : $this->tautan('bagikan'),
        ])->layout('layouts::app', ['judul' => 'Lihat seperti tamu', 'sub' => 'Pratinjau Undangan']);
    }
}
