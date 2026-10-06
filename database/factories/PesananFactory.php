<?php

namespace Database\Factories;

use App\Models\Pesanan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pesanan> */
class PesananFactory extends Factory
{
    protected $model = Pesanan::class;

    public function definition(): array
    {
        return [
            'nomor' => 'INV-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => User::factory(),
            'subtotal' => 249000,
            'diskon' => 0,
            'biaya' => 0,
            'kode_unik' => 0,
            'total' => 249000,
            'status' => Pesanan::MENUNGGU,
            'kedaluwarsa_at' => now()->addDay(),
            'pembeli_nama' => fake()->name(),
            'pembeli_email' => fake()->safeEmail(),
        ];
    }

    public function lunas(): static
    {
        return $this->state(fn () => ['status' => Pesanan::LUNAS, 'dibayar_at' => now()]);
    }

    public function kedaluwarsa(): static
    {
        return $this->state(fn () => ['kedaluwarsa_at' => now()->subHour()]);
    }
}
