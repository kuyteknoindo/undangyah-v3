<?php

namespace App\Livewire\Concerns;

use App\Services\KonfirmasiSandiAdmin;
use App\Support\KonfirmasiSandiGagal;
use Livewire\Attributes\Locked;

/**
 * Titik pengaman bersama untuk aksi admin yang sensitif. Dipakai bersama `<x-admin.dialog-konfirmasi-sandi>` (satu dialog
 * yang sama di semua halaman) dan layanan `KonfirmasiSandiAdmin` (stempel 5 menit di sesi, batas percobaan, log audit).
 *
 * Pemakaian: di AWAL metode aksi (sebelum mengubah apa pun), setelah cek admin dan validasi input:
 *
 *     if (! $this->butuhSandi('Hapus pengguna', 'jalankanAksi')) { return; }
 *
 * Bila belum dikonfirmasi, metode berhenti, dialog sandi terbuka, dan setelah sandi benar metode yang sama dipanggil
 * ulang otomatis. Karena pemeriksaan ada DI DALAM metode aksi, memanggilnya langsung lewat Livewire tanpa konfirmasi
 * tidak menjalankan apa pun. Sandi hanya hidup selama satu permintaan: properti dikosongkan segera setelah dipakai.
 */
trait MintaKonfirmasiSandi
{
    public bool $tampilKonfirmasiSandi = false;

    /** Isian dialog; SELALU dikosongkan di akhir tiap permintaan agar tidak ikut tersimpan di snapshot Livewire. */
    public string $sandiAdmin = '';

    #[Locked]
    public string $tertundaMetode = '';

    /** @var array<string,mixed> */
    #[Locked]
    public array $tertundaArg = [];

    #[Locked]
    public string $tertundaLabel = '';

    #[Locked]
    public string $tertundaPesan = '';

    /**
     * @param  string  $label  nama aksi untuk dialog dan log audit
     * @param  string  $metode  metode komponen ini yang dipanggil ulang setelah sandi benar
     * @param  array<string,mixed>  $arg  argumen bernama untuk pemanggilan ulang
     * @return bool true bila sudah dikonfirmasi (lanjutkan); false bila dialog sandi dibuka (hentikan)
     */
    protected function butuhSandi(string $label, string $metode, array $arg = []): bool
    {
        $admin = auth()->user();
        abort_unless($admin?->isAdmin() && ! $admin->ditangguhkan_at, 403);
        abort_unless(method_exists($this, $metode), 500);

        $layanan = app(KonfirmasiSandiAdmin::class);
        if ($layanan->berlaku($admin)) {
            return true;
        }

        $this->resetErrorBag('sandiAdmin');
        $this->sandiAdmin = '';
        $this->tertundaMetode = $metode;
        $this->tertundaArg = $arg;
        $this->tertundaLabel = $label;
        $this->tertundaPesan = $layanan->bisaKonfirmasi($admin)
            ? $layanan->pesanAwal($admin)
            : 'Akun ini tidak bisa mengonfirmasi dengan kata sandi (mis. sedang dalam sesi "masuk sebagai"), jadi aksi ini tidak tersedia.';
        $this->tampilKonfirmasiSandi = true;

        return false;
    }

    /** Dipanggil tombol "Konfirmasi" di dialog. Mengembalikan hasil metode yang tertunda (mis. unduhan/redirect). */
    public function konfirmasiSandi(): mixed
    {
        $admin = auth()->user();
        abort_unless($admin?->isAdmin() && ! $admin->ditangguhkan_at, 403);

        $sandi = $this->sandiAdmin;
        $this->sandiAdmin = '';
        $this->resetErrorBag('sandiAdmin');

        if ($this->tertundaMetode === '') {
            $this->tampilKonfirmasiSandi = false;

            return null;
        }

        try {
            app(KonfirmasiSandiAdmin::class)->konfirmasi($admin, $sandi, $this->tertundaLabel);
        } catch (KonfirmasiSandiGagal $e) {
            $this->addError('sandiAdmin', $e->getMessage());

            return null;
        }

        $metode = $this->tertundaMetode;
        $arg = $this->tertundaArg;
        $this->batalKonfirmasiSandi();

        return app()->call([$this, $metode], $arg);
    }

    public function batalKonfirmasiSandi(): void
    {
        $this->tampilKonfirmasiSandi = false;
        $this->tertundaMetode = '';
        $this->tertundaArg = [];
        $this->tertundaLabel = '';
        $this->tertundaPesan = '';
        $this->sandiAdmin = '';
        $this->resetErrorBag('sandiAdmin');
    }

    /** Dialog ditutup dari klien (X, Esc, klik latar): lupakan aksi yang tertunda. */
    public function updatedTampilKonfirmasiSandi(bool $nilai): void
    {
        if (! $nilai) {
            $this->batalKonfirmasiSandi();
        }
    }
}
