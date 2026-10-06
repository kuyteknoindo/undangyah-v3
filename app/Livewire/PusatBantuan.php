<?php

namespace App\Livewire;

use App\Models\BantuanArtikel;
use App\Models\BantuanSuara;
use App\Services\KontakLayanan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Pusat Bantuan: kategori, pencarian di server, FAQ accordion, dan suara "membantu / tidak" per pengguna. */
class PusatBantuan extends Component
{
    private const MAKS_KATA = 8;

    private const MAKS_ARTIKEL = 300;

    #[Url(as: 'q', history: false)]
    public string $cari = '';

    #[Url(as: 'kategori', history: false)]
    public string $kategori = 'semua';

    /** null = artikel pertama terbuka (bawaan); 0 = semua tertutup. */
    public ?int $buka = null;

    public function updatedCari(): void
    {
        $this->cari = mb_substr(trim($this->cari), 0, 120);
        $this->buka = null;
    }

    public function pilihKategori(string $kunci): void
    {
        $this->kategori = array_key_exists($kunci, BantuanArtikel::KATEGORI) ? $kunci : 'semua';
        $this->buka = null;
    }

    public function aturUlang(): void
    {
        $this->cari = '';
        $this->kategori = 'semua';
        $this->buka = null;
    }

    public function ubahBuka(int $id, bool $sedangBuka): void
    {
        $this->buka = $sedangBuka ? 0 : $id;
    }

    /** @param  string  $nilai  ya | tidak | batal */
    public function suara(int $id, string $nilai): void
    {
        $user = auth()->user();
        $artikel = BantuanArtikel::publik()->find($id);
        if (! $user || ! $artikel || ! in_array($nilai, ['ya', 'tidak', 'batal'], true)) {
            return;
        }

        $kunci = 'bantuan-suara:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 30)) {
            $this->dispatch('toast', pesan: 'Terlalu cepat. Coba lagi sebentar lagi.', tipe: 'galat');

            return;
        }
        RateLimiter::hit($kunci, 60);

        if ($nilai === 'batal') {
            BantuanSuara::where('bantuan_artikel_id', $artikel->id)->where('user_id', $user->id)->delete();

            return;
        }
        BantuanSuara::updateOrCreate(
            ['bantuan_artikel_id' => $artikel->id, 'user_id' => $user->id],
            ['membantu' => $nilai === 'ya'],
        );
    }

    public function render(KontakLayanan $kontak)
    {
        $kategori = array_key_exists($this->kategori, BantuanArtikel::KATEGORI) ? $this->kategori : 'semua';
        $kata = array_slice(preg_split('/\s+/u', mb_strtolower(trim($this->cari)), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAKS_KATA);

        $semua = BantuanArtikel::publik()->limit(self::MAKS_ARTIKEL)->get();
        $cocok = $semua->filter(function (BantuanArtikel $a) use ($kata) {
            $teks = mb_strtolower($a->judul.' '.$a->isi);

            return collect($kata)->every(fn ($k) => str_contains($teks, $k));
        })->values();
        $tampil = $cocok->filter(fn ($a) => $kategori === 'semua' || $a->kategori === $kategori)->values();

        $jumlah = ['semua' => $cocok->count()];
        foreach (array_keys(BantuanArtikel::KATEGORI) as $k) {
            $jumlah[$k] = $cocok->where('kategori', $k)->count();
        }

        $terbuka = $this->buka === null ? ($tampil->first()?->id ?? 0) : $this->buka;
        $suara = auth()->user()
            ? BantuanSuara::where('user_id', auth()->id())->whereIn('bantuan_artikel_id', $tampil->pluck('id'))->pluck('membantu', 'bantuan_artikel_id')
            : collect();

        $ringkasan = trim($this->cari) !== ''
            ? $tampil->count().' hasil untuk “'.trim($this->cari).'”'
            : 'Menampilkan '.$tampil->count().' dari '.$semua->count().' pertanyaan';

        return view('livewire.bantuan.index', [
            'artikel' => $tampil,
            'jumlah' => $jumlah,
            'kategoriAktif' => $kategori,
            'terbuka' => $terbuka,
            'suara' => $suara,
            'ringkasan' => $ringkasan,
            'kontak' => $kontak->ambil(),
            'tautan' => fn (BantuanArtikel $a) => $a->rute && Route::has($a->rute) ? route($a->rute) : null,
            'tautanSyarat' => Route::has('syarat') ? route('syarat') : null,
            'tautanHubungi' => Route::has('hubungi') ? route('hubungi') : null,
        ])->layout('layouts::app', ['judul' => 'Pusat Bantuan', 'sub' => 'Bantuan']);
    }
}
