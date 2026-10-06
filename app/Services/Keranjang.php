<?php

namespace App\Services;

/**
 * Keranjang belanja di sesi server (bukan localStorage). Hanya menyimpan KODE paket/add-on; harga selalu dihitung ulang
 * dari database oleh HitungPesanan, jadi isi sesi tidak pernah dipercaya sebagai harga.
 */
class Keranjang
{
    private const KUNCI = 'keranjang';

    /** @return array{paket:?string,addons:list<string>} */
    public function ambil(): array
    {
        $data = session(self::KUNCI, []);
        $paket = is_array($data) && is_string($data['paket'] ?? null) && $this->kodeValid($data['paket']) ? $data['paket'] : null;
        $addons = is_array($data) && is_array($data['addons'] ?? null)
            ? array_values(array_unique(array_filter($data['addons'], fn ($k) => is_string($k) && $this->kodeValid($k))))
            : [];

        return ['paket' => $paket, 'addons' => $addons];
    }

    public function isi(?string $paket, array $addons): void
    {
        session([self::KUNCI => [
            'paket' => $paket !== null && $this->kodeValid($paket) ? $paket : null,
            'addons' => array_values(array_unique(array_filter($addons, fn ($k) => is_string($k) && $this->kodeValid($k)))),
        ]]);
    }

    public function setPaket(?string $kode): void
    {
        $this->isi($kode, $this->ambil()['addons']);
    }

    public function ubahAddon(string $kode): bool
    {
        if (! $this->kodeValid($kode)) {
            return false;
        }
        $k = $this->ambil();
        $ada = in_array($kode, $k['addons'], true);
        $k['addons'] = $ada ? array_values(array_diff($k['addons'], [$kode])) : [...$k['addons'], $kode];
        $this->isi($k['paket'], $k['addons']);

        return ! $ada;
    }

    public function hapusAddon(string $kode): void
    {
        $k = $this->ambil();
        $this->isi($k['paket'], array_values(array_diff($k['addons'], [$kode])));
    }

    public function kosongkan(): void
    {
        session()->forget(self::KUNCI);
    }

    public function kosong(): bool
    {
        $k = $this->ambil();

        return $k['paket'] === null && $k['addons'] === [];
    }

    private function kodeValid(string $kode): bool
    {
        return (bool) preg_match('/^[a-z0-9-]{1,40}$/', $kode);
    }
}
