<?php

namespace App\Livewire;

use App\Livewire\Concerns\PilihUndanganMilik;
use App\Models\Tamu;
use App\Services\CatatPengiriman;
use App\Services\PesanUndangan;
use App\Services\StatistikUndangan;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/** Statistik satu undangan: kunjungan, alur tamu, jam dan hari ramai. Hanya data yang memang tercatat. */
class Statistik extends Component
{
    use PilihUndanganMilik;

    private const BELUM_RINGKAS = 6;

    private const BELUM_SEMUA = 50;

    public bool $siap = false;

    /** 7 | 30 | 90 (hari). */
    public int $rentang = 7;

    /** grafik | tabel */
    public string $tampilan = 'grafik';

    public bool $semuaBelum = false;

    public function mount(?string $undangan = null): void
    {
        $this->tentukanUndanganAwal($undangan);
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    protected function setelahPilihUndangan(): void
    {
        $this->semuaBelum = false;
    }

    public function pilihRentang(int $hari): void
    {
        $this->rentang = in_array($hari, StatistikUndangan::RENTANG, true) ? $hari : 7;
    }

    public function pilihTampilan(string $tampilan): void
    {
        $this->tampilan = $tampilan === 'tabel' ? 'tabel' : 'grafik';
    }

    public function ubahSemuaBelum(): void
    {
        $this->semuaBelum = ! $this->semuaBelum;
    }

    /** Pengguna membuka WhatsApp untuk tamu yang belum membuka: catat sebagai terkirim. */
    public function catatKirim(int $tamuId): void
    {
        $u = $this->undanganWajib();
        $tamu = Tamu::query()->where('undangan_id', $u->id)->findOrFail($tamuId);
        if (! $u->sudahTerbit()) {
            $this->dispatch('toast', pesan: 'Terbitkan undangan dulu sebelum mengirim.', tipe: 'galat');

            return;
        }
        if (app(CatatPengiriman::class)->catat(auth()->user(), $u, $tamu)) {
            $this->dispatch('toast', pesan: $tamu->nama.' ditandai terkirim.', tipe: 'sukses');
        } else {
            $this->dispatch('toast', pesan: 'Terlalu banyak pengiriman dalam semenit. Tunggu sebentar.', tipe: 'galat');
        }
    }

    public function render()
    {
        $daftar = $this->daftarUndangan();
        $u = $this->undanganTerpilih();
        $data = null;
        $belum = null;

        if ($this->siap && $u) {
            $layanan = app(StatistikUndangan::class);
            $data = $layanan->hitung($u, $this->rentang);
            $belum = $layanan->belumMembuka($u, $this->semuaBelum ? self::BELUM_SEMUA : self::BELUM_RINGKAS);
            $belum['baris'] = $this->barisBelum($u, $belum['daftar']);
        }

        return view('livewire.statistik.index', [
            'daftar' => $daftar,
            'undangan' => $u,
            'data' => $data,
            'belum' => $belum,
            'ringkas' => self::BELUM_RINGKAS,
            'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : '#',
        ])->layout('layouts::app', ['judul' => 'Statistik', 'sub' => 'Kelola undangan']);
    }

    /** @param iterable<Tamu> $tamu */
    private function barisBelum($u, iterable $tamu): array
    {
        if (! $u->sudahTerbit()) {
            return [];
        }
        $pesan = app(PesanUndangan::class);
        $templat = $pesan->daftar(auth()->user());
        $pengaturan = $pesan->pengaturan(auth()->user(), $templat);

        $baris = [];
        foreach ($tamu as $t) {
            $baris[$t->id] = PesanUndangan::urlWa($t->hp, $pesan->untukTamu($u, $t, $templat, $pengaturan));
        }

        return $baris;
    }
}
