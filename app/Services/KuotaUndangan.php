<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\Paket;
use App\Models\Pengaturan;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;

/**
 * Batas undangan dan kunci tema menurut paket pengguna. Aktif hanya bila Pengaturan `paket.aktif` benar;
 * tanpa itu semuanya tanpa batas.
 *
 * Pengaturan: paket.aktif (bool), paket.kuota (peta paket => jumlah, mis. {"gratis":1,"premium":3,"deluxe":10};
 * diisi dari katalog paket oleh SinkronKuotaPaket). Paket pengguna = `User::paketAktif()` (paket berbayar yang
 * masa aktifnya habis dihitung gratis). Add-on kuota yang dibeli (`efek.kuota_undangan`) menambah batas.
 */
class KuotaUndangan
{
    public function __construct(private AturanPaket $aturan) {}

    public function aktif(): bool
    {
        return (bool) Pengaturan::ambil('paket.aktif', false);
    }

    public function paket(User $user): string
    {
        $paket = $user->paketAktif();

        return isset($this->peringkat()[$paket]) ? $paket : 'gratis';
    }

    /**
     * Peringkat paket (kode => urutan naik); sumbernya `AturanPaket`, satu-satunya penentu tingkat paket.
     *
     * @return array<string,int>
     */
    public function peringkat(): array
    {
        return $this->aturan->peringkat();
    }

    /** Batas jumlah undangan, atau null bila tanpa batas. */
    public function batas(User $user): ?int
    {
        if (! $this->aktif()) {
            return null;
        }
        $kuota = Pengaturan::ambil('paket.kuota.'.$this->paket($user));

        return is_numeric($kuota) ? max(0, (int) $kuota) + $this->tambahan($user) : null;
    }

    /** Tambahan kuota dari add-on yang sudah dibeli. */
    public function tambahan(User $user): int
    {
        $milik = AddonPengguna::jumlahMilik($user->id);
        if ($milik === []) {
            return 0;
        }

        return (int) AddOn::whereIn('kode', array_keys($milik))->get()
            ->sum(fn (AddOn $a) => ((int) ($a->efek['kuota_undangan'] ?? 0)) * ($milik[$a->kode] ?? 0));
    }

    public function terpakai(User $user): int
    {
        return Undangan::where('user_id', $user->id)->count();
    }

    public function penuh(User $user): bool
    {
        $batas = $this->batas($user);

        return $batas !== null && $this->terpakai($user) >= $batas;
    }

    public function temaTerkunci(Tema $tema, User $user): bool
    {
        if (! $this->aktif()) {
            return false;
        }

        $peringkat = $this->peringkat();

        return ($peringkat[strtolower($tema->tier)] ?? 0) > ($peringkat[$this->paket($user)] ?? 0);
    }
}
