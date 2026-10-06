<?php

namespace Database\Factories;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notifikasi>
 */
class NotifikasiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipe' => Notifikasi::TIPE_SISTEM,
            'judul' => fake()->sentence(4),
            'isi' => fake()->sentence(10),
            'tautan' => null,
            'data' => null,
            'dibaca_at' => null,
            'disembunyikan_at' => null,
        ];
    }

    public function tipe(string $tipe): static
    {
        return $this->state(fn () => ['tipe' => $tipe]);
    }

    public function dibaca(): static
    {
        return $this->state(fn () => ['dibaca_at' => now()]);
    }

    public function disembunyikan(): static
    {
        return $this->state(fn () => ['disembunyikan_at' => now()]);
    }
}
