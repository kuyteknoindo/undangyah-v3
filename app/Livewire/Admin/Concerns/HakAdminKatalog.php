<?php

namespace App\Livewire\Admin\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/** Pengaman bersama halaman admin katalog penjualan: peran admin dicek ulang di tiap aksi, dan aksi tulis dibatasi lajunya. */
trait HakAdminKatalog
{
    private const BATAS_TULIS_PER_MENIT = 60;

    protected function pastikanAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    /** Pastikan admin, lalu hitung satu aksi tulis. false (dan toast galat) bila terlalu sering. */
    protected function izinTulis(): bool
    {
        $this->pastikanAdmin();
        $kunci = 'katalog-tulis:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_TULIS_PER_MENIT)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak perubahan dalam satu menit. Coba lagi sebentar lagi.', tipe: 'galat');

            return false;
        }
        RateLimiter::hit($kunci, 60);

        return true;
    }

    /** Pesan untuk aturan yang belum ada di lang/id/validation.php; Livewire menggabungkannya ke setiap validate(). */
    protected function messages(): array
    {
        return [
            'integer' => ':attribute harus berupa angka bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'in' => ':attribute tidak valid.',
            'date_format' => ':attribute tidak valid.',
            'array' => ':attribute tidak valid.',
        ];
    }

    protected function rp(int|float|null $angka): string
    {
        return 'Rp '.number_format((int) $angka, 0, ',', '.');
    }

    /** Buang semua selain digit; kosong tetap kosong. */
    protected function angka(mixed $nilai): string
    {
        return preg_replace('/\D+/', '', (string) $nilai) ?? '';
    }
}
